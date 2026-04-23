document.addEventListener("DOMContentLoaded", function(){

const STORAGE_KEY = "judgeia_chat_history_v1";

const chat = document.getElementById("judgeia-chat");
const button = document.getElementById("judgeia-button");
const closeBtn = document.getElementById("judgeia-close");
const minimizeBtn = document.getElementById("judgeia-minimize");
const toggleSizeBtn = document.getElementById("judgeia-toggle-size");
const sendBtn = document.getElementById("judgeia-send");
const input = document.getElementById("judgeia-input");
const messages = document.getElementById("judgeia-messages");
const loader = document.getElementById("judgeia-loader");
const clearBtn = document.getElementById("judgeia-clear");
const survey = document.getElementById("judgeia-survey");
const surveyComment = document.getElementById("judgeia-survey-comment");
const surveySendBtn = document.getElementById("judgeia-survey-send");
const surveySkipBtn = document.getElementById("judgeia-survey-skip");
const surveyStatus = document.getElementById("judgeia-survey-status");
const ratingButtons = Array.from(document.querySelectorAll(".judgeia-rating-btn"));

let surveyPending = false;
let surveyDismissed = false;
let selectedRating = 0;
let pendingCloseAction = null;

if(!chat || !button) return;

/* ================================
   RESTAURAR HISTÓRICO
================================ */

const savedHistory = localStorage.getItem(STORAGE_KEY);

if(savedHistory){
    messages.innerHTML = savedHistory;
} 
else if(typeof judgeia_ajax !== "undefined" && judgeia_ajax.welcome && judgeia_ajax.welcome.trim() !== ""){
    const welcomeDiv = document.createElement("div");
    welcomeDiv.className = "judgeia-message judgeia-ai";
    welcomeDiv.innerHTML = formatResponse(judgeia_ajax.welcome);
    messages.appendChild(welcomeDiv);
    saveHistory();
}

/* ================================
   CONTROLE ABRIR / FECHAR
================================ */

function openChat(){
    chat.classList.remove("judgeia-hidden");
    button.style.display = "none";
}

function closeChat(){
    if(shouldPromptSurvey()){
        pendingCloseAction = doCloseChat;
        openSurvey();
        return;
    }

    doCloseChat();
}

function doCloseChat(){
    chat.classList.add("judgeia-hidden");
    button.style.display = "flex";
    stopSpeech();
}

button.addEventListener("click", openChat);
if(closeBtn) closeBtn.addEventListener("click", closeChat);
if(minimizeBtn) minimizeBtn.addEventListener("click", closeChat);

/* ================================
   FULLSCREEN
================================ */

if(toggleSizeBtn){
    toggleSizeBtn.addEventListener("click", function(){
        chat.classList.toggle("judgeia-fullscreen");
        toggleSizeBtn.innerText = chat.classList.contains("judgeia-fullscreen") ? "🗗" : "⛶";
    });
}

/* ================================
   ENVIO
================================ */

if(sendBtn) sendBtn.addEventListener("click", sendMessage);

if(input){
    input.addEventListener("keypress", function(e){
        if(e.key === "Enter"){
            sendMessage();
        }
    });
}

function saveHistory(){
    localStorage.setItem(STORAGE_KEY, messages.innerHTML);
}

function hasConversationToRate(){
    return messages.querySelector(".judgeia-user") && messages.querySelector(".judgeia-ai");
}

function shouldPromptSurvey(){
    return survey && surveyPending && !surveyDismissed && hasConversationToRate();
}

function openSurvey(){
    if(!survey) return;
    survey.classList.remove("judgeia-hidden");
    surveyStatus.textContent = "";
}

function closeSurvey(){
    if(!survey) return;
    survey.classList.add("judgeia-hidden");
}

function finalizePendingAction(){
    const action = pendingCloseAction;
    pendingCloseAction = null;
    if(typeof action === "function"){
        action();
    }
}

function setSelectedRating(rating){
    selectedRating = rating;
    ratingButtons.forEach((buttonRef) => {
        const isActive = Number(buttonRef.dataset.rating) === rating;
        buttonRef.classList.toggle("is-active", isActive);
    });
}

function buildTranscript(){
    const items = Array.from(messages.querySelectorAll(".judgeia-message"));

    return items
        .map((item) => {
            const author = item.classList.contains("judgeia-user") ? "Usuario" : "IA";
            const content = (item.innerText || "").trim();
            return content ? `${author}: ${content}` : "";
        })
        .filter(Boolean)
        .join("\n\n");
}

function sendMessage(){

    const message = input.value.trim();
    if(!message) return;

    appendUser(message);
    input.value="";
    loader.classList.remove("judgeia-hidden");

    if(typeof judgeia_ajax === "undefined") return;

    fetch(judgeia_ajax.ajax_url,{
        method:"POST",
        headers:{ "Content-Type":"application/x-www-form-urlencoded"},
        body:"action=judgeia_send_message&nonce="+judgeia_ajax.nonce+"&message="+encodeURIComponent(message)
    })
    .then(r=>r.json())
    .then(data=>{
        loader.classList.add("judgeia-hidden");
        if(data.success){
            appendAI(data.data.response);
            surveyPending = true;
            surveyDismissed = false;
            saveHistory();
        }
    })
    .catch(()=>{
        loader.classList.add("judgeia-hidden");
    });
}

function appendUser(text){
    const div=document.createElement("div");
    div.className="judgeia-message judgeia-user";
    div.innerText=text;
    messages.appendChild(div);
    scrollToBottom();
    saveHistory();
}

function appendAI(text){

    const div = document.createElement("div");
    div.className = "judgeia-message judgeia-ai";
    div.innerHTML = formatResponse(text);

    const speakBtn = document.createElement("button");
    speakBtn.className = "judgeia-audio-btn";
    speakBtn.innerHTML = "🗣️";
    speakBtn.title = "Ouvir resposta";

    speakBtn.addEventListener("click", function(){
        if(speechSynthesis.speaking){
            stopSpeech();
        } else {
            speak(text, speakBtn);
        }
    });

    div.appendChild(speakBtn);
    messages.appendChild(div);
    scrollToBottom();
}

/* ================================
   LIMPAR CONVERSA
================================ */

if(clearBtn){
    clearBtn.addEventListener("click", function(){
        if(!confirm("Deseja limpar a conversa?")) return;

        if(shouldPromptSurvey()){
            pendingCloseAction = clearConversation;
            openSurvey();
            return;
        }

        clearConversation();
    });
}

function clearConversation(){
    messages.innerHTML = "";
    localStorage.removeItem(STORAGE_KEY);
    stopSpeech();
    surveyPending = false;
    surveyDismissed = false;
    setSelectedRating(0);
    if(surveyComment) surveyComment.value = "";
    closeSurvey();
}

if(surveySkipBtn){
    surveySkipBtn.addEventListener("click", function(){
        surveyDismissed = true;
        closeSurvey();
        finalizePendingAction();
    });
}

if(surveySendBtn){
    surveySendBtn.addEventListener("click", function(){
        if(selectedRating < 1){
            surveyStatus.textContent = "Selecione uma nota de 1 a 5.";
            return;
        }

        surveyStatus.textContent = "Enviando...";
        surveySendBtn.disabled = true;

        const params = new URLSearchParams();
        params.set("action", "judgeia_send_feedback");
        params.set("nonce", judgeia_ajax.nonce);
        params.set("rating", String(selectedRating));
        params.set("comment", surveyComment ? surveyComment.value.trim() : "");
        params.set("transcript", buildTranscript());
        params.set("page_url", window.location.href);

        fetch(judgeia_ajax.ajax_url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: params.toString()
        })
        .then((response) => response.json())
        .then((data) => {
            if(data.success){
                surveyPending = false;
                surveyDismissed = true;
                surveyStatus.textContent = judgeia_ajax.survey?.success || "Obrigado pela sua avaliação.";
                setTimeout(() => {
                    closeSurvey();
                    finalizePendingAction();
                }, 700);
                return;
            }

            surveyStatus.textContent = data?.data?.message || judgeia_ajax.survey?.error || "Não foi possível enviar sua avaliação agora.";
        })
        .catch(() => {
            surveyStatus.textContent = judgeia_ajax.survey?.error || "Não foi possível enviar sua avaliação agora.";
        })
        .finally(() => {
            surveySendBtn.disabled = false;
        });
    });
}

ratingButtons.forEach((buttonRef) => {
    buttonRef.addEventListener("click", function(){
        setSelectedRating(Number(buttonRef.dataset.rating));
        if(surveyStatus) surveyStatus.textContent = "";
    });
});

/* ================================
   SISTEMA DE VOZ ESTÁVEL
================================ */

let currentUtterance = null;

function speak(text, buttonRef){

    if(!('speechSynthesis' in window)){
        alert("Seu navegador não suporta leitura de voz.");
        return;
    }

    stopSpeech();

    const cleanText = text
        .replace(/(\d+\.\s)/g, "$1... ")
        .trim();

    currentUtterance = new SpeechSynthesisUtterance(cleanText);
    currentUtterance.lang = "pt-BR";
    currentUtterance.rate = 0.93;
    currentUtterance.pitch = 1.05;

    const voices = speechSynthesis.getVoices();
    const ptVoice = voices.find(v => v.lang === "pt-BR") || voices.find(v => v.lang.startsWith("pt"));
    if(ptVoice) currentUtterance.voice = ptVoice;

    currentUtterance.onstart = () => {
        if(buttonRef){
            buttonRef.innerHTML = "⏹";
            buttonRef.title = "Parar leitura";
        }
    };

    currentUtterance.onend = () => {
        if(buttonRef){
            buttonRef.innerHTML = "🗣️";
            buttonRef.title = "Ouvir resposta";
        }
    };

    speechSynthesis.speak(currentUtterance);
}

function stopSpeech(){
    if('speechSynthesis' in window){
        speechSynthesis.cancel();
    }
}

/* ================================
   FORMATAÇÃO
================================ */

function formatResponse(text){

    if(!text) return "";

    let formatted = text.trim();

    formatted = formatted.replace(/(\.\s*)(\d+\.\s)/g, "$1\n\n$2");

    formatted = formatted.replace(/\n\n+/g, "</p><p>");
    formatted = formatted.replace(/\n/g, "<br>");

    formatted = formatted.replace(/(\d+\.\s)/g, "<strong>$1</strong>");
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");

    return "<p>" + formatted + "</p>";
}

function scrollToBottom(){
    messages.scrollTop = messages.scrollHeight;
}

});