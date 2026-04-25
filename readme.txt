=== Judge IA Plugin ===
Contributors: judgeia
Tags: ai, chatbot, artificial intelligence, gemini, openai, wordpress chat
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chatbot de Inteligência Artificial para WordPress com suporte a Gemini e OpenAI, controle de limites, persistência de histórico e interface moderna.

== Description ==

Judge IA Plugin é um chatbot de Inteligência Artificial projetado para funcionar em qualquer site WordPress.

Compatível com:

- Google Gemini (2.x e 3.x)
- OpenAI (GPT models)

O plugin oferece:

- Botão flutuante arrastável
- Janela de chat moderna
- Upload de avatar personalizado
- Indicador visual de status da API (LED)
- Persistência de conversa no navegador
- Histórico salvo no banco de dados
- Controle de limite diário de requisições
- Configuração de temperatura e tokens
- Leitura por voz (Text-to-Speech)
- Limpeza automática de caracteres markdown

Ideal para:

- Escritórios de advocacia
- Sites institucionais
- Atendimento automatizado
- Assistente jurídico
- Suporte inteligente

== Features ==

= Interface =

- Botão flutuante reposicionável
- Drag & Drop
- Minimizar / Maximizar
- Avatar configurável
- Cor primária personalizada
- Indicador de API online/offline
- Loader animado
- Limpeza automática de formatação markdown

= Inteligência Artificial =

- Seleção entre Gemini e OpenAI
- Compatível com Gemini 2.x e 3.x
- Controle de Temperature
- Controle de Max Tokens
- System Prompt personalizado

= Controle e Segurança =

- Limite diário configurável por usuário/IP
- Bloqueio automático ao atingir limite
- Contador visual de requisições restantes
- Verificação por nonce
- Sanitização de dados

= Persistência =

- Histórico salvo no banco de dados
- Persistência local via localStorage
- Identificação por usuário logado ou visitante

== Installation ==

1. Faça upload da pasta `judge-ia-plugin` para o diretório `/wp-content/plugins/`
2. Ative o plugin no menu "Plugins" do WordPress
3. Acesse "Judge IA" no menu lateral
4. Configure sua API Key
5. Escolha o modelo desejado
6. Defina limite diário e tokens
7. Salve as configurações

== Build do pacote ZIP ==

Para gerar o pacote instalavel no formato correto do WordPress (com pasta raiz unica), execute na raiz do projeto:

powershell -ExecutionPolicy Bypass -File .\build-plugin-zip.ps1

O arquivo sera gerado em:

dist\judge-ia-plugin-x.y.z-install.zip

== Configuration ==

= Aba Geral =
- System Prompt
- Temperature
- Max Tokens
- Limite diário de requisições

= Aba Provedores =
- Escolha entre Gemini ou OpenAI
- API Keys
- Modelos

= Aba Aparência =
- Cor primária
- Posição do botão
- Upload de avatar

== Frequently Asked Questions ==

= O plugin funciona com qualquer tema? =
Sim. Ele utiliza hooks padrão do WordPress e não depende de construtores específicos.

= O limite diário é por usuário ou por IP? =
Ambos. Usuários logados são identificados por user_id e visitantes por hash de IP + navegador.

= Posso usar Gemini 3.x? =
Sim. Basta inserir o nome do modelo no campo apropriado.

= O plugin armazena conversas? =
Sim. As conversas são salvas em tabela própria no banco de dados.

= É possível desativar o limite diário? =
Sim. Basta definir o limite como 0.

== Changelog ==

= 1.1 =
* Implementado limite diário configurável
* Contador visual de requisições restantes
* Bloqueio automático ao atingir limite
* Histórico persistente no banco
* Sistema de logs no admin
* Melhorias de segurança e sanitização
* Estabilização da arquitetura

= 1.0 =
* Lançamento inicial
* Integração Gemini e OpenAI
* Botão flutuante arrastável
* Persistência local
* Upload de avatar
* Indicador de status da API

== Upgrade Notice ==

= 1.1 =
Versão estável com controle de limites e persistência completa. Recomendada para todos os usuários.
