document.addEventListener("DOMContentLoaded", function(){

const STORAGE_KEY = "judgeia_chat_history_v1";

const chat = document.getElementById("judgeia-chat");
const button = document.getElementById("judgeia-button");
const widget = document.querySelector(".judgeia-widget");
const header = chat ? chat.querySelector(".judgeia-header") : null;
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
const WIDGET_POS_KEY = "judgeia_widget_position_v1";

let surveyPending = false;
let surveyDismissed = false;
let selectedRating = 0;
let pendingCloseAction = null;
let wasDragged = false;
let dragState = null;

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

button.addEventListener("click", function(){
    if(wasDragged){
        wasDragged = false;
        return;
    }

    openChat();
});
if(closeBtn) closeBtn.addEventListener("click", closeChat);
if(minimizeBtn) minimizeBtn.addEventListener("click", closeChat);

initWidgetDrag();

/* ================================
   FULLSCREEN
================================ */

if(toggleSizeBtn){
    toggleSizeBtn.addEventListener("click", function(){
        chat.classList.toggle("judgeia-fullscreen");
        const isFull = chat.classList.contains("judgeia-fullscreen");
        toggleSizeBtn.innerHTML = isFull 
            ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>' 
            : '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>';
    });
}

/* ================================
   ENVIO
================================ */

if(sendBtn) sendBtn.addEventListener("click", sendMessage);

if(input){
    input.addEventListener("keydown", function(e){
        if(e.key === "Enter" && !e.shiftKey){
            e.preventDefault();
            sendMessage();
        }
    });

    input.addEventListener("input", function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight <= 120 ? this.scrollHeight : 120) + 'px';
        if (this.value === "") {
            this.style.height = 'auto';
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
    input.style.height = 'auto';
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
            return;
        }

        const errorMessage = data?.data?.message || "Nao foi possivel obter resposta agora.";
        appendError(errorMessage);
        saveHistory();
    })
    .catch(()=>{
        loader.classList.add("judgeia-hidden");
        appendError("Falha de conexao. Tente novamente em instantes.");
        saveHistory();
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
    speakBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
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

function appendError(text){
    const div = document.createElement("div");
    div.className = "judgeia-message judgeia-ai judgeia-error";
    div.innerText = text;
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

// Lógica da Pesquisa Multiblocos
const surveyQuestions = {};

function initSurvey() {
    const questions = document.querySelectorAll(".judgeia-survey-question");
    questions.forEach(q => {
        const id = q.dataset.question;
        surveyQuestions[id] = 0;
        const stars = q.querySelectorAll(".judgeia-stars button");
        stars.forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                const val = parseInt(btn.dataset.val);
                surveyQuestions[id] = val;
                
                // Remove 'is-active' de todos os botões desta questão e adiciona apenas ao clicado
                stars.forEach(b => b.classList.remove("is-active"));
                btn.classList.add("is-active");
                
                console.log(`Questão ${id} respondida com: ${val}`);
            });
        });
    });
}

initSurvey();


if(surveySkipBtn){
    surveySkipBtn.addEventListener("click", function(){
        surveyDismissed = true;
        closeSurvey();
        finalizePendingAction();
    });
}

if(surveySendBtn){
    surveySendBtn.addEventListener("click", function(){
        
        surveyStatus.textContent = "Enviando...";
        surveySendBtn.disabled = true;

        const params = new URLSearchParams();
        params.set("action", "judgeia_send_feedback");
        params.set("nonce", judgeia_ajax.nonce);
        
        // Coletar respostas das perguntas
        for (const [key, value] of Object.entries(surveyQuestions)) {
            params.set(key, String(value));
        }

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
                }, 1000);
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
            buttonRef.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>';
            buttonRef.title = "Parar leitura";
        }
    };

    currentUtterance.onend = () => {
        if(buttonRef){
            buttonRef.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
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

    // Preservar quebras de linha duplas/triplas antes de outras formatações
    formatted = formatted.replace(/\n\n\n+/g, '<br><br><br>');
    formatted = formatted.replace(/\n\n/g, '<br><br>');
    formatted = formatted.replace(/\n/g, '<br>');

    // Negrito para títulos numerados e Markdown
    formatted = formatted.replace(/(\d+\.\s.*?:)/g, "<strong>$1</strong>");
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");

    return "<div>" + formatted + "</div>";
}

function scrollToBottom(){
    messages.scrollTop = messages.scrollHeight;
}

function initWidgetDrag(){
    if(!widget || window.matchMedia("(max-width: 768px)").matches){
        return;
    }

    restoreWidgetPosition();

    if(button){
        button.addEventListener("mousedown", beginDrag);
    }

    if(header){
        header.style.cursor = "grab";
        header.addEventListener("mousedown", beginDrag);
    }

    document.addEventListener("mousemove", onDrag);
    document.addEventListener("mouseup", endDrag);
}

function beginDrag(event){
    if(event.button !== 0 || !widget){
        return;
    }

    dragState = {
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
        offsetX: event.clientX - widget.getBoundingClientRect().left,
        offsetY: event.clientY - widget.getBoundingClientRect().top
    };

    if(header){
        header.style.cursor = "grabbing";
    }

    event.preventDefault();
}

function onDrag(event){
    if(!dragState || !widget){
        return;
    }

    const deltaX = Math.abs(event.clientX - dragState.startX);
    const deltaY = Math.abs(event.clientY - dragState.startY);
    if(!dragState.moved && (deltaX > 3 || deltaY > 3)){
        dragState.moved = true;
        wasDragged = true;
    }

    if(!dragState.moved){
        return;
    }

    const maxLeft = Math.max(0, window.innerWidth - widget.offsetWidth);
    const maxTop = Math.max(0, window.innerHeight - widget.offsetHeight);

    const nextLeft = clamp(event.clientX - dragState.offsetX, 0, maxLeft);
    const nextTop = clamp(event.clientY - dragState.offsetY, 0, maxTop);

    widget.style.left = `${nextLeft}px`;
    widget.style.top = `${nextTop}px`;
    widget.style.right = "auto";
    widget.style.bottom = "auto";
}

function endDrag(){
    if(!dragState){
        return;
    }

    if(dragState.moved && widget){
        persistWidgetPosition();
        window.setTimeout(() => {
            wasDragged = false;
        }, 120);
    }

    if(header){
        header.style.cursor = "grab";
    }

    dragState = null;
}

function clamp(value, min, max){
    return Math.min(Math.max(value, min), max);
}

function persistWidgetPosition(){
    if(!widget){
        return;
    }

    const left = parseInt(widget.style.left || "", 10);
    const top = parseInt(widget.style.top || "", 10);

    if(Number.isNaN(left) || Number.isNaN(top)){
        return;
    }

    localStorage.setItem(WIDGET_POS_KEY, JSON.stringify({ left, top }));
}

function restoreWidgetPosition(){
    if(!widget){
        return;
    }

    const raw = localStorage.getItem(WIDGET_POS_KEY);
    if(!raw){
        return;
    }

    try {
        const saved = JSON.parse(raw);
        if(typeof saved.left !== "number" || typeof saved.top !== "number"){
            return;
        }

        const maxLeft = Math.max(0, window.innerWidth - widget.offsetWidth);
        const maxTop = Math.max(0, window.innerHeight - widget.offsetHeight);

        widget.style.left = `${clamp(saved.left, 0, maxLeft)}px`;
        widget.style.top = `${clamp(saved.top, 0, maxTop)}px`;
        widget.style.right = "auto";
        widget.style.bottom = "auto";
    } catch (_error) {
        localStorage.removeItem(WIDGET_POS_KEY);
    }
}

});