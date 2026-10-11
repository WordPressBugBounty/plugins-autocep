=== AutoCEP ===
Contributors: wanderson100
Donate link: https://tesw.com.br/
Tags: cep, frete, correios, checkout woocommerce, melhor envio
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.48
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

O melhor plugin de CEP e frete do Brasil: autocompletar de endereço, simulador e tabela de frete por CEP e peso. Completo e 100% gratuito.

== Description ==

### O melhor plugin de CEP e frete do Brasil — completo e 100% gratuito

O **AutoCEP** transforma a logística da sua loja WooCommerce: autocompleta o endereço do cliente ao digitar o CEP e simula o frete em tempo real — na página do produto, no carrinho e no checkout — eliminando as duas maiores causas de abandono de carrinho no e-commerce brasileiro: o atrito de preencher um formulário longo e a incerteza do valor do frete antes da decisão de compra.

Desenvolvido pela equipe de engenharia da TESW, em parceria estratégica com a Ritmo Digital, todos os módulos — tabela de frete própria por CEP e peso, frete por Km, Growth Marketing (previsão de entrega e barra de frete grátis), retirada em loja por proximidade, personalização visual completa e o dashboard de Analytics — vêm desbloqueados desde a instalação, sem cobrança de mensalidade, sem versão "Pro" e sem recursos limitados atrás de upgrade.

### Novidades da versão 2.48

* **Tabela de Frete por CEP e Peso (novo método de envio)**: cadastre sua própria planilha de frete — cole as linhas direto do Excel/Google Planilhas ou importe um arquivo CSV. Várias opções por CEP (ex.: Econômico e Expresso), prazo em dias úteis integrado à previsão de entrega e taxa adicional opcional. Ao salvar, a Zona de Entrega é limitada sozinha às faixas de CEP da tabela e posicionada na ordem certa.
* **Checkout Moderno em 3 etapas (novo)**: transforma o checkout clássico em Identificação → Entrega → Pagamento, com resumo do pedido sempre visível, validação a cada etapa, modo compacto e layout que se adapta à forma de pagamento (Pix, cartão ou boleto). Na página de pedido recebido, destaca o QR Code Pix com botão "Copiar código Pix".
* **Analytics do Checkout (funil de conversão)**: na aba Analytics, veja a taxa de conversão do checkout, onde os clientes desistem (entrada → entrega → pagamento → finalizar → pedido), receita e ticket médio, tempo médio até o pedido, conversão por forma de pagamento (Pix, cartão, boleto) e por aparelho (celular x computador) e os campos que mais travam os clientes. Anônimo: nenhum nome, e-mail ou endereço é gravado.
* **Sugestões de endereço ao digitar**: enquanto o cliente digita a rua, aparece uma lista de ruas reais (ex.: "Av Paulista" → "Avenida Paulista (até 610 - lado par) — Bela Vista, São Paulo/SP · CEP 01310-000"); ao escolher, preenche rua, bairro, cidade, estado e o CEP. Grátis pelo ViaCEP (com cidade e estado) ou pelo Google Maps, se a loja tiver chave.
* **Autocompletar no Checkout em Blocos**: o endereço agora é preenchido também no novo checkout em blocos do WooCommerce (padrão nas lojas novas).
* **Compatível com HPOS e WooCommerce 11**: fim do aviso "1 plugin incompatível detectado (AutoCEP)" — pode manter o armazenamento de pedidos de alto desempenho ativado.
* **Mais rápido quando uma API cai**: a API de CEP ou o Google Maps que ficar lento/fora do ar entra em pausa automática e a próxima alternativa assume na hora — sem o cliente ficar esperando o "carregando".
* **Sem zoom no iPhone/iPad**: o campo de CEP não amplia mais a página ao ser tocado, e campo e botão ficaram maiores no celular.
* **Correções**: título em branco e janela de configuração vazia nos métodos de envio do AutoCEP; "CEP não encontrado" indevido quando uma API bloqueava o servidor; consulta à ApiCEP corrigida.

### Assista ao Vídeo de Apresentação e Demonstração

https://youtu.be/EkEC8CJ_H5c

### Documentação Oficial do Plugin
Guia completo de instalação, configuração de cada aba, integração com transportadoras e personalização visual: [tesw.com.br/documentacao-autocep](https://tesw.com.br/documentacao-autocep)

### Por que instalar o AutoCEP

* **Painel de Latência em Tempo Real (ms) e Prioridade de APIs**: o AutoCEP consulta ViaCEP, BrasilAPI e ApiCEP. Na aba "Frete e Busca de CEP", o botão "Testar CEP Agora" mede a latência de cada provedor em milissegundos, para você definir a ordem de prioridade (1ª, 2ª, 3ª escolha). Se uma API oscilar, a próxima assume automaticamente em cascata.
* **100% gratuito de verdade**: tabela de frete, frete por Km, simulador de produto, Growth Marketing, previsão de entrega, personalização visual e dashboard de Analytics vêm todos desbloqueados, sem custo.
* **Zero divergência de valores**: o simulador não inventa estimativas — ele lê as Zonas de Entrega reais do seu WooCommerce (transportadoras, frete fixo, frete grátis ou a Tabela de Frete do próprio AutoCEP). O valor simulado é o mesmo cobrado no checkout.
* **Fallback automático para o Google Maps**: no cálculo de frete por Km e na retirada por proximidade, se a Distance Matrix API do Google sofrer timeout ou instabilidade, o plugin recalcula por estimativa (linha reta × 1.3), sem deixar de mostrar uma opção de frete ao cliente.
* **Navegação ágil com submenus dedicados**: atalhos diretos com ícones próprios para Visão Geral, Analytics, Growth Marketing, Retirada em Loja, Integrações de Frete, Suporte Técnico e Diagnóstico.
* **Suporte integrado ao painel**: abra chamados direto do WordPress, com número sequencial (ex.: AC-000123) e botões de contato rápido (e-mail e WhatsApp) que já carregam uma mensagem pronta com o site e a versão do plugin.
* **Cache duplo de alta performance**: Cache de CEPs (dias configuráveis) e Cache de Cotações de Frete (horas configuráveis), deixando simulações repetidas quase instantâneas e reduzindo chamadas a APIs de transportadoras como Melhor Envio e Frenet.
* **Pausa automática de APIs lentas**: se uma API de CEP ou o Google Maps não responder, ela é pulada por alguns minutos e a próxima alternativa assume — só o primeiro cliente sente a demora, nunca a loja inteira.
* **Seleção real no checkout e no carrinho**: clicar em "Selecionar" aplica de verdade aquele método à sessão do WooCommerce, sempre revalidado no servidor antes de ser aplicado.
* **Compatibilidade universal com CSS de prioridade garantida**: funciona com o checkout clássico e o Checkout em Blocos do WooCommerce, Fluid Checkout, CartFlows e Elementor Pro — as cores e tipografia configuradas no painel sobrepõem até temas com estilos globais agressivos.

### Funcionalidades Incluídas (Todas Gratuitas)

* **Autocompletar de Endereço Inteligente**: preenchimento automático de Rua, Bairro, Cidade e Estado (Cobrança e Entrega) no checkout clássico e no Checkout em Blocos, foco automático no campo "Número", detecção dos campos de bairro mais comuns (`billing_neighborhood`, `billing_bairro`, `billing_district`) e aviso para CEPs sem rua específica cadastrada.
* **Checkout Moderno (Multietapas) e Gerenciador de Campos**: checkout clássico dividido em Identificação, Entrega e Pagamento, com campos extras e campos padrão que você esconde ou torna obrigatórios pelo painel, com barra de progresso, resumo do pedido fixo (recolhível no celular), validação de e-mail, telefone e CEP a cada etapa, escolha de frete em cartões, modo compacto (campos opcionais atrás de "+ adicionar") e mensagens por forma de pagamento (Pix, cartão, boleto) detectadas automaticamente nos gateways brasileiros. Usa os campos originais do WooCommerce, então gateways, plugins de CPF/CNPJ e o autocompletar de CEP continuam funcionando. Mobile-first e compatível com Astra, Hello Elementor, GeneratePress e outros temas.
* **Sugestões de Endereço ao Digitar**: lista de ruas reais no campo Endereço, com o trecho de numeração de ruas longas, entendendo abreviações (Av, R., Al., Dr., Prof.…) e sem CEPs de grandes usuários/caixas postais. Ao escolher, preenche rua, bairro, cidade, estado e o CEP — ideal para quem não sabe o CEP. Grátis via ViaCEP (precisa de cidade e estado) ou via Google Places (opcional, com a chave da loja, consultado pelo servidor). Funciona com teclado (setas, Enter, Esc) e toque.
* **Tabela de Frete por CEP e Peso**: método de envio próprio em WooCommerce > Configurações > Entrega. Cole a planilha do Excel/Google Planilhas ou importe um CSV com as colunas `cep_inicial; cep_final; peso_min; peso_max; valor; prazo; nome`. Várias opções de frete por CEP, prazo integrado à previsão de entrega, taxa adicional, aviso de linhas com erro e limitação automática da Zona de Entrega às faixas de CEP da tabela. Calculada no próprio servidor da loja, sem depender de nenhuma API externa.
* **Simuladores de Frete (Produto, Carrinho e Checkout)**: cotação na página do produto (considerando quantidade e variação), no carrinho (somando os itens) e no checkout (cálculo rápido por CEP). Pode ser posicionado manualmente com o shortcode `[shipping_calculator_on_product_page]`, e desliga automaticamente a caixa nativa do Melhor Envio para não duplicar campos.
* **Growth Marketing — Previsão de Entrega e Frete Grátis Dinâmico**: datas estimadas de entrega (ex.: "Chega entre Quarta-feira (25/10) e Sexta-feira (27/10)"), com opção de contar só dias úteis, e Barra de Progresso de Frete Grátis com a tag dinâmica `{missing}` (ex.: "Faltam {missing} para você ganhar Frete Grátis!"), detectando automaticamente as regras de frete grátis já configuradas no WooCommerce.
* **Retirada em Loja por Proximidade e Frete por Km (Google Maps)**: cálculo de distância rodoviária real com fórmula de Taxa Fixa + (Km × Valor por Km), raio e valor mínimo configuráveis, e testes integrados de Status Rápido da API e Diagnóstico Detalhado de distância entre dois CEPs.
* **Integrações de Frete**: testes de conexão em tempo real com as APIs do Melhor Envio e da Frenet — o token é detectado automaticamente a partir do que já está salvo nos plugins oficiais de cada transportadora (nenhum campo para preencher), confirmando se ele é válido e se a API está no ar, sem precisar simular um pedido completo.
* **Personalização Visual Completa**: cor de fundo, borda e texto da caixa e do botão, arredondamento (nenhum/pequeno/médio/grande), tipografia (tamanho e peso da fonte) e espaçamento (compacto/normal/espaçoso), incluindo cores próprias para a barra de frete grátis em progresso e ao ser conquistada.
* **Pagamento na Entrega**: dinheiro com troco, Pix, débito, crédito ou vale na entrega, liberado só para os fretes que você escolher (ex.: Motoboy e Retirada), com a forma e o troco no pedido e nos e-mails.
* **Pós-Compra — Status do Pedido e Página de Obrigado**: mensagem em tempo real quando o pagamento é aprovado (ou falha, é cancelado...), na página de pedido recebido, e redirecionamento opcional para a sua página de agradecimento depois da aprovação.
* **Analytics — Funil do Checkout**: taxa de conversão e desistência do checkout etapa por etapa, receita e ticket médio, tempo médio até o pedido, conversão por forma de pagamento e por aparelho, e ranking dos campos que mais geram erro — dados anônimos, com filtro de 7/30/90 dias.
* **Analytics — Inteligência de Frete**: frete médio calculado vs. pago, taxa de conversão vs. desistência, ranking dos CEPs e estados que mais geram cotações, e os métodos de frete mais escolhidos nos pedidos.
* **Diagnóstico e Detecção de Produtos sem Peso**: histórico de falhas e timeouts de transportadoras. Quando um método habilitado na zona não retorna taxa, o plugin aponta produtos sem peso/dimensões cadastrados (Produtos > Editar Produto > Entrega) como causa provável — comum em transportadoras privadas via API (ex.: Jadlog, Azul Ecommerce), que costumam exigir esse dado para cotar, diferente dos Correios.

### Segurança e Padrões de Engenharia

* **Nonces em toda ação AJAX** (`check_ajax_referer`) e verificação de capacidade (`current_user_can('manage_options')`) em todas as ações administrativas.
* **Sanitização e validação de entrada** em todo dado recebido do cliente, e **consultas ao banco sempre parametrizadas** (`$wpdb->prepare`).
* **Escape de saída** (`esc_html`, `esc_attr`, `esc_url`) em todo HTML gerado dinamicamente.
* **Tokens de API (Google Maps, Melhor Envio, Frenet) nunca expostos ao front-end** — usados apenas em requisições feitas pelo servidor.
* **Upload de anexos do chamado de suporte validado pelo conteúdo real do arquivo** (`wp_check_filetype_and_ext()`), não apenas pelo tipo declarado pelo navegador.
* **Nenhum script de terceiros carregado por CDN externo** — bibliotecas empacotadas localmente (ver "Bibliotecas de Terceiros Empacotadas" abaixo).
* Nenhum dado pessoal do cliente é repassado a servidores externos além das APIs de CEP/frete estritamente necessárias para o cálculo em curso.

== Installation ==

1. No painel do WordPress, vá em **Plugins > Adicionar Novo > Enviar Plugin**.
2. Selecione o arquivo `autocep.zip` e clique em **Instalar Agora**.
3. Clique em **Ativar Plugin**.
4. O autocompletar de CEP no checkout já entra em funcionamento imediatamente.
5. No menu **AutoCEP**, ative os simuladores, a previsão de entrega, configure a identidade visual e teste a velocidade das APIs de CEP.

**Atualizando de uma versão anterior**: basta substituir os arquivos do plugin pelos desta versão (ou desativar e reativar depois de sobrescrever). As configurações já salvas são preservadas.

== Como usar cada aba do painel ==

**Geral & Busca de CEP** — liga/desliga o autocompletar de Cobrança e Entrega, escolhe as APIs de CEP ativas e a ordem de prioridade, mapeia o campo de bairro, ativa o foco automático e define a duração do cache.

**Frete na Página de Produto / Carrinho / Checkout** — ativa cada simulador, escolhe a posição na página e o título da caixa.

**Checkout** (também no menu lateral) — reúne o simulador de frete do checkout e o Checkout Moderno: ativa o checkout em 3 etapas e o modo compacto, define os títulos das etapas, o texto do botão, a cor de destaque, as mensagens por forma de pagamento (Pix, cartão, boleto) e o destaque do Pix na página de pedido recebido. Funciona com o checkout clássico; com Checkout em Blocos, Fluid Checkout ou CartFlows ele se desativa sozinho (a aba mostra o motivo).

**Growth Marketing** — ativa a Previsão de Entrega e a Barra de Progresso de Frete Grátis Dinâmico.

**Retirada em Loja** — chave de API do Google Maps, cadastro de lojas físicas e configuração do Frete por Km.

**Integrações de Frete** — testa a conexão com as APIs do Melhor Envio e da Frenet.

**Tabela de Frete** (em WooCommerce > Configurações > Entrega) — abra ou crie uma Zona de Entrega, clique em "Adicionar método de entrega" e escolha "Tabela de Frete — AutoCEP". Em "Editar", cole a planilha ou use "Importar arquivo CSV" e salve. Com "Limitar a zona" ligado, a zona passa a atender só as faixas de CEP da tabela e é colocada acima de zonas mais gerais (ex.: Brasil inteiro). Dica: use uma zona só para a tabela.

**Mensagens & UX** — edita todos os textos exibidos ao cliente e o bloqueio do botão "Finalizar Compra" durante requisições.

**Aparência** — personaliza cores, arredondamento, tipografia e espaçamento das caixas de frete.

**Analytics** — dashboard de inteligência de frete.

**Suporte Técnico** — abre chamados com anexos, acompanha "Meus Chamados" e traz os contatos diretos da equipe TESW.

**Diagnóstico e Logs** — status em tempo real das APIs de CEP e histórico de falhas.

== Como o simulador de frete se relaciona com o frete real do pedido ==

* **Na página do produto**: a caixa é **informativa** — o item ainda não está no carrinho, então não há "seleção" possível ali.
* **No carrinho e no checkout**: cada opção simulada tem um botão **"Selecionar"** que aplica de verdade aquele método ao pedido, usando o mesmo mecanismo de sessão que o WooCommerce usa nos próprios botões de rádio de frete.

O CEP e o método enviados no clique de "Selecionar" são sempre revalidados no servidor no momento da seleção — o plugin nunca aplica cegamente o que o navegador envia.

== Frequently Asked Questions ==

= O plugin é realmente 100% gratuito? =

Sim. Não existe versão "Lite" limitada nem cobrança para liberar frete por Km, simulador de produto, previsão de entrega, barra de frete grátis, personalização visual ou o dashboard de Analytics.

= Como funciona a escolha da API de CEP mais rápida? =

Na aba "Frete e Busca de CEP", o botão "Testar CEP Agora" mede a latência (em milissegundos) do ViaCEP, BrasilAPI e ApiCEP. Você define a ordem de prioridade (1º, 2º e 3º) e o plugin faz o fallback automático caso a primeira da lista oscile.

= O AutoCEP calcula o frete sozinho ou precisa de transportadoras cadastradas? =

Os dois. Ele lê em tempo real as transportadoras já configuradas em WooCommerce > Configurações > Entrega — Frete Fixo, Frete Grátis, Retirada Local, Correios, Melhor Envio e Frenet — e também traz a **Tabela de Frete** própria, para você cobrar pela sua planilha de faixas de CEP e peso, sem depender de transportadora.

= Posso cadastrar minha própria planilha de frete do Excel? =

Sim. Adicione o método "Tabela de Frete — AutoCEP" numa Zona de Entrega, clique em "Editar" e cole as linhas copiadas do Excel/Google Planilhas ou importe o arquivo CSV (UTF-8 ou o "CSV separado por ponto e vírgula" padrão do Excel). As colunas são: `cep_inicial; cep_final; peso_min; peso_max; valor; prazo; nome`. Deixe `peso_max` vazio para "sem limite"; `prazo` e `nome` são opcionais. Cadastre o peso dos produtos, pois é ele que define a faixa usada.

= Criei um campo extra e ele não aparece no checkout =

O AutoCEP só adiciona campos que o WooCommerce aceita. Em AutoCEP > Checkout > Campos Extras, campos com problema (ex.: lista sem opções, identificador em conflito) mostram um aviso com o motivo. Os campos só valem com o Checkout Moderno ativado.

= Como ativar o checkout em etapas? =

Em AutoCEP > Checkout (também no menu lateral do AutoCEP), marque "Ativar Checkout Moderno" e salve. Ele funciona com o checkout clássico do WooCommerce (página "Finalizar compra" com o shortcode [woocommerce_checkout]). Se a sua página usa o Checkout em Blocos, o Fluid Checkout ou o CartFlows, que já têm layout próprio, o AutoCEP não altera o checkout e explica o motivo na própria aba.

= O Checkout Moderno mostra o QR Code do Pix antes de finalizar? =

Não — o QR Code e o código copia e cola são gerados pelo gateway de pagamento só depois que o pedido é criado. Na etapa de pagamento, o AutoCEP explica isso ao cliente; na página de pedido recebido, destaca o QR Code e adiciona o botão "Copiar código Pix" (opção ativada por padrão).

= Como aceitar pagamento na entrega (dinheiro com troco, maquininha, Pix)? =

Ative "Pagamento na Entrega — AutoCEP" em WooCommerce > Configurações > Pagamentos (ou pelo botão na aba Checkout do AutoCEP). Escolha as formas aceitas e, se quiser, os fretes em que ele aparece — por exemplo, só Motoboy e Retirada em Loja, deixando de fora Correios e transportadoras. No dinheiro, o cliente informa o troco; o pedido mostra quanto troco levar.

= Aparece "1 plugin incompatível detectado (AutoCEP)" no WooCommerce =

Atualize para a versão 2.48 ou superior: ela declara compatibilidade com o armazenamento de pedidos de alto desempenho (HPOS) e com o Checkout em Blocos, e o aviso desaparece. Não é preciso desativar o HPOS.

= A simulação diz "Nenhuma opção de frete disponível", mas os métodos estão configurados =

O WooCommerce usa só a **primeira Zona de Entrega** da lista que combina com o CEP do cliente. Se houver uma zona acima sem métodos ativos (ou com métodos que não atendem aquele CEP), as zonas de baixo nunca são consultadas — o próprio WooCommerce mostra um aviso laranja nessas zonas. Ordene as zonas da mais específica (CEPs, cidades) para a mais geral (Brasil) e clique em "Limpar cache" no AutoCEP.

= Como posso ajudar o projeto, já que ele é gratuito? =

Avaliando! Em **AutoCEP > Avalie o Plugin** você dá sua nota em estrelas, comenta e envia sugestões direto para a equipe. Se gostou, deixe também sua avaliação no [WordPress.org](https://wordpress.org/support/plugin/autocep/reviews/#new-post) — isso ajuda outros lojistas a encontrar o AutoCEP.

= Como integrar com Melhor Envio ou Frenet? =

Instale o plugin oficial da transportadora, cole o token nas configurações dela e adicione o método na sua Zona de Entrega do WooCommerce. O AutoCEP reconhece o preço e o prazo automaticamente. A aba "Integrações de Frete" do AutoCEP testa apenas se o token está válido e a API está no ar — o cálculo real do frete continua sendo feito pelo plugin oficial da transportadora.

= Por que algumas transportadoras não aparecem na simulação, mesmo habilitadas na zona? =

Normalmente não é um bug — é a própria transportadora rejeitando a cotação silenciosamente (sem erro, só sem preço). A causa mais comum é o produto estar sem peso ou dimensões cadastrados em Produtos > Editar Produto > Entrega; transportadoras privadas via API (ex.: Jadlog, Azul Ecommerce) costumam exigir esse dado, enquanto os Correios tendem a ser mais tolerantes. A aba Diagnóstico e Logs aponta exatamente qual método não retornou taxa.

= O que acontece se o Google Maps ficar fora do ar? =

O AutoCEP aciona automaticamente o cálculo por contingência (distância em linha reta × 1.3), para que o cliente continue vendo uma opção de frete local sem interrupção.

= O autocompletar de CEP parou de funcionar depois que troquei de tema/checkout? =

O plugin observa automaticamente mudanças no formulário de checkout, continuando a funcionar mesmo em checkouts de terceiros como Fluid Checkout e CartFlows. Se ainda assim não funcionar, confira em Diagnóstico e Logs se alguma API está offline.

= Por que aparecem duas caixas de frete na página de produto? =

Provavelmente outro plugin (ex.: Melhor Envio) também insere sua própria caixa automática. O AutoCEP desliga a do Melhor Envio automaticamente quando o detecta instalado.

= Aparece "CEP não encontrado" mesmo digitando um CEP que parece válido =

Confira se o CEP foi digitado corretamente. Se ele realmente não existir na base dos Correios, nenhuma das três APIs (ViaCEP, BrasilAPI, ApiCEP) vai encontrá-lo — isso é esperado. Se o CEP existe, o mais provável é uma API bloqueando o servidor temporariamente por excesso de consultas: a partir da versão 2.48 isso é tratado como falha da API (que entra em pausa e é substituída pela próxima), e não como CEP inexistente. Clique em "Limpar cache" na aba Frete e Busca de CEP para apagar resultados antigos.

= Clicar em uma opção de frete simulada aplica esse frete ao pedido? =

No carrinho e no checkout, sim. Na página do produto, a simulação é apenas informativa, já que o item ainda não está no carrinho.

== Compatibility ==

* **WooCommerce**: 4.0 ou superior, testado até a 11.1. Compatível com o armazenamento de pedidos de alto desempenho (HPOS).
* **Checkouts compatíveis**: checkout clássico e Checkout em Blocos do WooCommerce, Fluid Checkout, CartFlows e montagens em etapas via Elementor Pro. O Checkout Moderno (multietapas) do AutoCEP funciona sobre o checkout clássico.
* **Integradores de frete**: Melhor Envio, Frenet, Correios, Frete Fixo, Frete Grátis, Retirada Local e a Tabela de Frete do AutoCEP.
* **Dispositivos**: desktop, Android, iPhone e iPad (sem zoom automático no campo de CEP).
* **APIs de CEP**: ViaCEP, BrasilAPI e ApiCEP.
* **APIs de rota**: Google Maps Distance Matrix API (opcional, usada apenas nos módulos de Frete por Km e Retirada por Proximidade).

== Documentação de Terceiros ==

Este plugin pode consultar as seguintes APIs de CEP, conforme configuração:
* [ViaCEP](https://viacep.com.br/)
* [BrasilAPI](https://brasilapi.com.br/)
* [ApiCEP](https://apicep.com/)

Para as Sugestões de Endereço ao Digitar (opcional, ativável na aba Frete e Busca de CEP), o servidor da loja envia o texto digitado na rua, a cidade e o estado para:
* [ViaCEP](https://viacep.com.br/) — busca de ruas na base dos Correios.
* [Photon / Komoot](https://photon.komoot.io/) — ruas do [OpenStreetMap](https://www.openstreetmap.org/copyright) em cidades de CEP único ([termos](https://photon.komoot.io/)).
* [Google Places API](https://developers.google.com/maps/documentation/places/web-service) — somente se a loja configurar a própria chave de API ([termos](https://cloud.google.com/maps-platform/terms), [privacidade](https://policies.google.com/privacy)).

Nenhum nome, e-mail, CPF ou número de casa do cliente é enviado nessas consultas.

O cálculo de frete usa exclusivamente as Zonas de Entrega já configuradas em WooCommerce > Configurações > Entrega — o AutoCEP não se conecta diretamente a nenhuma transportadora; ele lê os métodos que a própria loja já configurou (nativos do WooCommerce ou de plugins como Melhor Envio e Frenet).

= Bibliotecas de Terceiros Empacotadas =

* **Chart.js 4.4.0** (licença MIT) — usado apenas nos gráficos da aba AutoCEP > Analytics, empacotado localmente em `assets/js/vendor/` (nunca carregado de um CDN externo).

== Screenshots ==

1. Frete e Busca de CEP — autocompletar no checkout e prioridade das APIs de CEP.
2. Simulador de Frete na Página de Produto.
3. Simulador de Frete no Carrinho.
4. Simulador de Frete no Checkout.
5. Retirada em Loja — testes e diagnóstico da API do Google Maps.
6. Integrações de Frete — testes de conexão com Melhor Envio e Frenet.
7. Personalização Visual na aba Aparência.
8. Dashboard Analytics de Frete.
9. Simulador de frete no carrinho com as opções de retirada e frete grátis.
10. Endereço preenchido automaticamente pelo CEP no checkout.

== Ajuda e Suporte ==

Em caso de dúvidas ou problemas:
* Verifique se o CEP é válido.
* Confirme se o plugin e as APIs desejadas estão ativos em AutoCEP > Geral & Busca de CEP.
* Consulte a aba "Diagnóstico e Logs" para verificar falhas recentes.
* Abra um chamado direto pela aba "Suporte Técnico" do painel.

== Desenvolvedores & Créditos ==

O AutoCEP é mantido pela **TESW — Tecnologia em Soluções Web**, em parceria estratégica com a **Ritmo Digital**.

**Wanderson Cesar**
* Desenvolvedor, Fundador da TESW e Criador do AutoCEP.
* Versão 2.0 (reformulação completa do plugin) e todas as versões seguintes.
* Perfil WordPress.org: [profiles.wordpress.org/wanderson100](https://profiles.wordpress.org/wanderson100)
* LinkedIn: [linkedin.com/in/wanderson-cesar-2710a621b](https://www.linkedin.com/in/wanderson-cesar-2710a621b)

**Alfonso Fontenla Neto**
* Diretor Executivo da TESW e CEO da Ritmo Digital.
* LinkedIn: [linkedin.com/in/alfonso-fontenla-neto](https://www.linkedin.com/in/alfonso-fontenla-neto)

**Contribuição pontual na versão 1.5**: Samuel Canale.

= Páginas Oficiais =

* [Plugins TESW](https://tesw.com.br/plugins)
* [Site TESW](https://tesw.com.br)

= Suporte =

* E-mail: [teswinfor@gmail.com](mailto:teswinfor@gmail.com)
* WhatsApp: [+55 (83) 99407-5812](https://wa.me/5583994075812)
* Ou direto pela aba **AutoCEP > Suporte Técnico** do painel, que já abre com número de chamado e contexto do site preenchidos.

== Licença ==

GPLv2 ou posterior.

== Changelog ==

= 2.48 =
* **Segurança — limite de uso por visitante** nos endpoints públicos que consultam serviços externos ou gravam no banco (busca de CEP, sugestões de endereço, detalhes do Google, funil do checkout, status do pedido): impede que robôs usem a chave de segurança da página para gerar milhares de consultas (inclusive cobradas pelo Google). Limites bem acima do uso normal; ajustáveis pelo filtro `autocep_rate_limit` (0 desliga).
* **Limpeza ao excluir o plugin** (`uninstall.php`): remove as tabelas, configurações e caches do AutoCEP. Dados dos pedidos (forma de pagamento na entrega, troco, campos extras) são mantidos. Para manter tudo: `add_filter('autocep_keep_data_on_uninstall', '__return_true');`.
* **Privacidade**: o CEP dos clientes só é gravado no log do servidor (`error_log`) com o modo de depuração do WordPress ativo; os registros normais ficam na aba Diagnóstico e Logs.
* **Compatibilidade**: funil do checkout não depende mais de `wp_date()` (WordPress 5.3+), mantendo o suporte declarado ao WordPress 5.0.
* **Verificação de campos extras do checkout**: antes de gravar, na tela do painel e no checkout, o AutoCEP confere se o WooCommerce aceita o campo (rótulo preenchido, tipo suportado, lista com opções, identificador sem conflito com outros campos). Campo que não passa não é adicionado (com aviso do motivo) e nunca quebra o checkout. Limite de 20 campos extras.
* **Novo: Sugestões de Endereço ao Digitar** (aba Frete e Busca de CEP): lista de ruas reais enquanto o cliente digita o endereço; ao escolher, preenche rua, bairro, cidade, estado e CEP. ViaCEP (grátis) ou Google Places (opcional). Entende abreviações de tipo e títulos (Av, R., Al., Dr., Prof.), corrige tipo digitado errado ("Rua Paulista" → Avenida Paulista), mostra o trecho de numeração de ruas longas, ignora CEPs de grandes usuários e caixas postais, e, em cidades de CEP único (sem ruas na base dos Correios, comum em cidades pequenas), sugere as ruas do OpenStreetMap (Photon) com o CEP geral oficial. Resultados em cache.
* **Novo: Status do Pedido em Tempo Real e Página de Obrigado** (aba Pós-Compra, opcional): na página de pedido recebido e na página do pedido em Minha Conta, o AutoCEP acompanha o status enquanto o cliente está na tela (ex.: pagando o Pix). Quando muda, mostra uma mensagem configurável por status (aprovado, concluído, em espera, falhou, cancelado, reembolsado) e, com o pagamento aprovado, leva o cliente para a sua página de agradecimento (com {pedido} na URL). Pedidos com Pix/boleto pendente continuam na página do WooCommerce até o pagamento — o cliente não perde o QR Code. Seguro (só o dono do pedido consulta, pela chave do pedido), leve (só nessas páginas, a cada 5 s, até 15 min, pausando com a aba em segundo plano) e compatível com HPOS.
* **Novo: Pagamento na Entrega — AutoCEP** (forma de pagamento): o cliente escolhe como vai pagar ao receber — dinheiro (com "Troco para quanto?" e "Não preciso de troco"), Pix na entrega, cartão de débito/crédito na maquininha ou vale-refeição/alimentação. A loja escolhe as formas aceitas, para quais fretes ele aparece (ex.: só Motoboy e Retirada) e o status do pedido. A forma e o troco ficam salvos no pedido (HPOS) e aparecem na página de pedido recebido, em Minha Conta, nos e-mails e na tela do pedido. Configuração em WooCommerce > Pagamentos ou pelo atalho na aba Checkout do AutoCEP.
* **"Entregar em um endereço diferente" no Checkout Moderno**: começa desmarcado (o cliente marca se quiser; opção na aba Checkout) e, quando marcado, os campos de entrega têm o mesmo tamanho e a mesma grade dos de cobrança (antes ficavam estreitos e com um espaço vazio antes de "Nome").
* **Tamanho do Checkout Moderno personalizável**: largura do formulário, largura do resumo do pedido e altura dos campos (aba Checkout).
* **Corrigida a rolagem lateral da página no Checkout Moderno** com plugins que usam Select2 (ex.: "Tipo de Pessoa" do plugin de CPF/CNPJ): o select original escondido pelo Select2 recebia a largura dos campos e empurrava a página para o lado.
* **Conta do cliente no Checkout Moderno**: escolha no AutoCEP se a conta é obrigatória, opcional ou dispensada (sem mexer nas configurações do WooCommerce). O login do topo ("Já é cliente? Clique aqui") foi levado para dentro da etapa Identificação — um único lugar com senha — e o aviso de conta mostra o e-mail que o cliente digitou ({email}).
* **Aviso de criação de conta no Checkout Moderno**: na etapa Identificação, quem não tem conta vê por que e como a conta é criada (conta obrigatória com senha, senha automática por e-mail ou conta opcional — o texto se adapta à configuração da loja) e o link "Já tem conta? Entrar", que abre o login do próprio checkout. Texto personalizável na aba Checkout.
* **Corrigido estado errado no frete (ex.: CEP do Piauí aparecendo como "Entrega para Paraíba")**: o simulador gravava só o CEP novo no cliente e mantinha o estado e a cidade da busca anterior; e, quando o CEP não era encontrado, usava o estado guardado na sessão. Agora o CEP, o estado e a cidade são gravados juntos; sem resposta das APIs, o estado vem das faixas oficiais de CEP dos Correios; a calculadora "Mudar endereço" do carrinho também passa a usar o estado e a cidade do CEP; e todo cálculo de frete no Brasil usa o estado do CEP para escolher a Zona de Entrega. CEP confirmado como inexistente pelas APIs mostra "CEP não encontrado" em vez de calcular frete.
* **Fim do "carregando" a cada letra no checkout**: digitar rua, número, complemento ou bairro não dispara mais o recálculo de frete e totais do WooCommerce (que bloqueava o resumo a cada pausa na digitação). CEP, cidade, estado e país continuam recalculando normalmente.
* **Novo: Funil do Checkout na aba Analytics** — taxa de conversão do Checkout Moderno, desistência em cada etapa, receita e ticket médio, tempo médio até o pedido, conversão por forma de pagamento (Pix/cartão/boleto) e por aparelho (celular/computador) e os campos com mais erros. Cada passo conta uma vez por cliente por dia; pedidos de outros caminhos não entram no funil; nenhum dado pessoal é gravado (só um código de sessão anônimo). Segue a opção "Coletar Estatísticas" e o botão "Limpar" da aba.
* **Novo: Checkout Moderno (Multietapas)** — aba "Checkout" (também no menu lateral): checkout clássico em 3 etapas (Identificação, Entrega, Pagamento) com resumo do pedido fixo, validação por etapa (e-mail, telefone, CEP, frete escolhido), modo compacto, layout por forma de pagamento (Pix, cartão, boleto — tipo detectado automaticamente pelo gateway) e destaque do QR Code/código Pix com botão de copiar na página de pedido recebido. Inclui gerenciador de campos (esconder empresa, complemento, sobrenome, telefone e observações; definir obrigatório/opcional; renomear) e campos extras (texto, número, data, lista, caixa de seleção) salvos no pedido e exibidos no admin e nos e-mails. O simulador de frete do AutoCEP fica no topo da etapa Entrega (ou oculto), sincronizado com o CEP, e o frete é recalculado assim que o CEP fica completo. Formulário compacto no estilo Fluid Checkout, alinhado com o resumo do pedido (cupom de desconto dentro do resumo, aplicado pelo próprio WooCommerce): coluna do formulário mais estreita e campos pequenos e do mesmo tamanho (38px, em duas colunas iguais no computador, sem buracos causados pelas divisórias que o WooCommerce e plugins de CPF/CNPJ inserem entre os campos), simulador de frete com visual moderno dentro da etapa e layout ajustado de 320px a telas largas. Os arquivos CSS/JS do plugin passam a ter a data de modificação na versão (?ver=), para que plugins de cache como o WP Rocket nunca sirvam uma cópia antiga após atualizar. Volta automaticamente para a etapa do campo com erro se o WooCommerce recusar o pedido. Desativa-se sozinho com Checkout em Blocos, Fluid Checkout e CartFlows.
* **Novo método de envio "Tabela de Frete — AutoCEP"**: cadastre sua própria planilha de frete por faixa de CEP e peso em WooCommerce > Configurações > Entrega > (Zona) > Adicionar método. Cole as linhas direto do Excel/Google Planilhas ou importe um arquivo CSV, com as colunas `cep_inicial; cep_final; peso_min; peso_max; valor; prazo; nome`. Linhas com nomes diferentes viram opções de frete distintas (ex.: Econômico e Expresso); o prazo aparece no nome do frete e alimenta a previsão de entrega; taxa adicional opcional. Funciona no carrinho, checkout (clássico e em blocos) e no simulador de frete do produto.
* **Compatibilidade com HPOS (armazenamento de pedidos de alto desempenho)**: o plugin agora declara compatibilidade com as tabelas de pedidos do WooCommerce, removendo o aviso "1 plugin incompatível detectado (AutoCEP)".
* Estatística de "métodos de envio mais usados" passa a ler os pedidos da tabela HPOS quando ela está ativa (mantém a leitura tradicional quando desativada).
* **Autocompletar de CEP no Checkout em Blocos**: o preenchimento automático do endereço agora funciona também no checkout em blocos (padrão nas instalações novas do WooCommerce), atualizando rua, bairro (no Complemento), cidade e estado diretamente no formulário. O plugin também declara compatibilidade com o Checkout em Blocos.
* **Corrigido zoom automático no iPhone/iPad** ao tocar no campo de CEP do simulador de frete: em telas de toque o campo agora usa fonte de 16px (limite abaixo do qual o Safari do iOS amplia a página), com campo e botão um pouco maiores para facilitar o toque. O zoom manual da página continua disponível. O campo também passa a sugerir o CEP salvo no aparelho.
* **Corrigido título em branco e janela de configuração vazia** nos métodos de envio do AutoCEP (Retirada em Loja e Tabela de Frete) em WooCommerce > Configurações > Entrega: os campos de configuração não eram carregados pelo WooCommerce.
* **Tabela de Frete limita a zona automaticamente**: ao salvar, as faixas de CEP da tabela são unidas e gravadas em "Limitar para CEPs específicos" da Zona de Entrega (opção "Limitar a zona", ativada por padrão). Se uma zona mais geral (ex.: Brasil inteiro) estiver acima na lista e impedindo a tabela de ser usada, a zona da tabela é movida para cima dela automaticamente; zonas mais específicas (por CEP ou estado) mantêm a prioridade. Isso só acontece quando a zona tem apenas a Tabela de Frete ativa (zonas compartilhadas com outros métodos não são alteradas). Ao desativar ou excluir a tabela, a zona que ficar sem métodos ativos desce para o fim da lista, para não bloquear as demais zonas (ex.: Melhor Envio); ao reativar, sobe de novo. A importação de CSV também passou a aceitar arquivos salvos pelo Excel em ANSI/Windows-1252, sem quebrar acentos.
* **Desempenho: busca de CEP e Retirada em Loja mais rápidas quando uma API está lenta ou fora do ar**: a API que falhar por conexão/timeout fica em pausa por alguns minutos (CEP: 5 min; Google Maps: 10 min) e é pulada, em vez de cada cliente esperar o timeout de novo; timeouts reduzidos (APIs de CEP 8s → 5s, Google Maps 12s → 6s, OpenStreetMap 8s → 5s); CEPs inexistentes ficam memorizados por 6 horas. Os testes de diagnóstico do painel ignoram a pausa.
* **Corrigido "CEP não encontrado" para CEPs válidos**: respostas de erro das APIs (bloqueio por excesso de consultas, erro 5xx, página HTML) eram tratadas como "CEP inexistente"; agora contam como falha da API (que entra em pausa) e só um "não existe" explícito da API é considerado. Corrigida também a consulta à ApiCEP, que usava o CEP sem hífen e sempre recebia "429 Blocked by flood".
* Testado com WooCommerce 11.1.

= 2.47 =
* **Nova aba "Avalie o Plugin"** (também no submenu e no cabeçalho do painel): avaliação de 1 a 5 estrelas com comentário e sugestões de melhoria, salva no site e enviada à equipe TESW. Notas 4 e 5 exibem um convite para repetir a avaliação no WordPress.org.
* Novo vídeo de apresentação ([youtu.be/EkEC8CJ_H5c](https://youtu.be/EkEC8CJ_H5c)), incorporado na Descrição do plugin no WordPress.org e na aba "Avalie o Plugin"; links do painel atualizados.

= 2.46 =
* **Aba "Integrações de Frete" não pede mais token manual**: o teste de conexão do Melhor Envio agora lê automaticamente o token que o plugin oficial dele já tem salvo (`wpmelhorenvio_token`/`wpmelhorenvio_token_sandbox`, respeitando o ambiente ativo); o teste da Frenet lê o token e o CEP de origem diretamente da instância do método oficial configurada numa Zona de Entrega. Nenhum dado é duplicado ou digitado de novo no AutoCEP.
* **Corrigido falso "Token Inválido" no teste do Melhor Envio**: o teste sempre chamava a API de produção (`www.melhorenvio.com.br`), mesmo quando o token detectado era de ambiente Sandbox — um token de sandbox nunca é aceito no host de produção (e vice-versa), pois são hosts diferentes (`api.melhorenvio.com` para produção, `sandbox.melhorenvio.com.br` para sandbox), confirmado no código-fonte oficial do plugin. Agora o host é escolhido conforme o ambiente detectado.
* Removida a imagem de logo da página de Créditos (reduz o tamanho do pacote).
* Seção de créditos do README expandida: papéis, perfis (WordPress.org/LinkedIn) e parceria com a Ritmo Digital.
* Removida a página "Créditos" do painel administrativo (e o respectivo link na listagem de Plugins) — as fotos `afonso.png`/`wanderson.png`, que só eram usadas ali, também foram removidas.
* Removidos todos os comentários do código PHP (incluindo PHPDoc e `phpcs:ignore`), a pedido explícito, para a preparação final de lançamento. Comentários de JavaScript/CSS embutidos em strings PHP (painel administrativo) foram mantidos intocados, por segurança — essa é a mesma região onde um bug de aspas de SVG já havia quebrado o site numa versão anterior.
* **Corrigido erro fatal introduzido pela remoção de comentários**: em `class-autocep-shipping.php`, dois comentários de fim de linha terminavam na mesma linha de uma tag `?>` de fechamento do PHP (ex.: `echo $x; // comentário ?></div>`). Como o PHP trata `?>` como um encerrador implícito de comentário de linha, e a remoção só considerava quebra de linha como fim de comentário, o `?></div>` acabou apagado junto — quebrando a sintaxe e derrubando o site com "Parse error" ao instalar. Corrigido restaurando as tags removidas nos dois pontos afetados; conferida a ausência do mesmo padrão em todo o restante do código.
* **Corrigido erro fatal na instalação — cabeçalho do plugin ausente**: o bloco de comentário `Plugin Name: AutoCEP` no topo do `autocep.php` também tinha sido removido junto com os demais comentários, mas esse bloco é metadado obrigatório que o WordPress lê diretamente do arquivo (sem executar o PHP) para reconhecer que é um plugin válido — sem ele, o instalador reportava "Nenhum plugin válido foi encontrado". Restaurado (enxuto, só com os campos que o WordPress efetivamente lê).
* Todos os links do README convertidos para hyperlinks reais (`[texto](url)`) — antes eram URLs soltas, sem link clicável, em vários pontos do documento.
* Adicionadas as 8 capturas de tela reais do painel (`screenshot-1.png` a `screenshot-8.png` em `wp-org-assets/`), com a seção Screenshots do README atualizada para descrever cada uma.

= 2.45 =
* **Corrigido erro fatal crítico introduzido na v2.12.0**: os ícones de mapa+pin (cabeçalho do painel) e de headset (aba Suporte Técnico) usavam URIs de SVG com aspas simples não escapadas dentro de uma string PHP também delimitada por aspas simples (`wp_add_inline_style('common', '...')`) — a primeira aspa do SVG fechava a string prematuramente, tornando o restante do arquivo um código PHP inválido e derrubando o site inteiro com "erro crítico" assim que o plugin era instalado. Corrigido substituindo as aspas simples do SVG por `%27` (URL-encoded), consistente com o restante do data URI já percent-encoded.
* Preparação de lançamento para o repositório oficial do WordPress: número de versão sincronizado em todo o plugin (header, constante `AUTOCEP_VERSION`, cache-busting de scripts/estilos).
* **Corrigida violação real das diretrizes do repositório**: a aba Analytics carregava a biblioteca Chart.js dinamicamente de um CDN externo (`cdn.jsdelivr.net`) no navegador do admin — não permitido pelas diretrizes do WordPress.org. Chart.js 4.4.0 agora é empacotado localmente em `assets/js/vendor/` e enfileirado via `wp_enqueue_script()`, sem nenhuma dependência de servidor de terceiros.
* Revisão de comentários no código: removidos os poucos comentários puramente redundantes encontrados; mantidos todos os comentários `phpcs:ignore` (exigidos pela conformidade real com o WPCS) e todo o racional de compatibilidade/segurança documentado.
* README reformulado com descrição completa de todos os módulos, FAQ ampliado e link para a [documentação oficial](https://tesw.com.br/documentacao-autocep).
* Links diretos para a Documentação Oficial e o Vídeo de Demonstração adicionados ao cabeçalho do painel administrativo e à aba Suporte Técnico.

= 2.12.0 =
* **Nova aba "Integrações de Frete"**: testes de conexão em tempo real para as APIs do Melhor Envio (valida o Token de Acesso via endpoint autenticado de perfil) e da Frenet (valida o Token de Integração via cotação de teste), com feedback visual verde/vermelho/âmbar e detalhamento técnico da falha quando aplicável. Não substitui os plugins oficiais dessas transportadoras — é só um diagnóstico.
* **Identidade visual do painel administrativo** atualizada para uma paleta com mais tons de azul (cabeçalho, badge do menu "+" e destaques), reduzindo o uso de amarelo/branco predominante.
* **Ícone do menu lateral** substituído por um mapa dobrado com pin de localização em destaque; **ícone da aba Suporte Técnico** substituído do antigo "SOS" (boia salva-vidas) por um headset de atendimento.
* **Segurança reforçada**: validação dos anexos do formulário de chamado de suporte agora verifica o conteúdo real do arquivo (`wp_check_filetype_and_ext()`), não apenas o tipo declarado pelo navegador; corrigida uma falha de XSS armazenado no JSON injetado nos gráficos do Dashboard de Analytics (adicionadas as flags `JSON_HEX_TAG`/`AMP`/`APOS`/`QUOT`).
* Auditoria completa de segurança e resiliência: revisadas todas as rotas AJAX (nonces e `current_user_can`), todas as consultas SQL (uso de `$wpdb->prepare`) e o tratamento de erros de todas as APIs externas (timeouts, HTTP 4xx/5xx, JSON corrompido) — nenhum outro problema encontrado.

= 2.3.5 =
* Vídeo demonstrativo adicionado no topo da Descrição do README, no formato que o WordPress.org converte automaticamente em player incorporado.
* Removido o arquivo de vídeo local do pacote (`store-assets/`), já hospedado no YouTube — reduz bastante o tamanho do .zip do plugin.
* Créditos esclarecidos: versão 2.0 criada pela equipe TESW, com desenvolvimento principal de Wanderson Cesar.

= 2.3.4 =
* Corrigido: ao selecionar um frete simulado no checkout, os campos de Cidade e Estado podiam continuar mostrando o endereço antigo mesmo com o CEP já atualizado, quando o autocompletar de Cobrança/Entrega estava desligado nas configurações. Como essa sincronização acontece por uma ação explícita do cliente (clicar em "Selecionar"), agora ela sempre atualiza o endereço completo (rua/bairro/cidade/estado), independente dessa opção.
* A sincronização de endereço ao selecionar um frete agora espera a busca terminar de verdade antes de recalcular o checkout, em vez de um tempo fixo — mais confiável em conexões lentas.
* Adicionados materiais de divulgação do plugin (ícone, banner e capturas de tela do painel) na pasta `store-assets/`, e seção de Screenshots no README.

= 2.3.3 =
* Corrigido: ao selecionar um frete simulado no checkout quando a etapa "Método de Entrega" já estava renderizada com uma opção diferente marcada, o próprio recálculo nativo do WooCommerce reenviava o valor do rádio ainda marcado na tela (o antigo) e sobrescrevia a seleção feita — mostrando "Frete selecionado com sucesso" mas mantendo o método/valor anterior. Agora o rádio nativo correspondente é marcado antes do recálculo, garantindo que a escolha feita na simulação seja a que efetivamente é enviada e aplicada.

= 2.3.2 =
* Card de simulação de frete menor e mais compacto (menos padding, fonte e espaçamentos reduzidos), para ocupar menos espaço na página de produto/carrinho/checkout.
* Corrigida (definitivamente) a aparência "torta" do texto dentro do botão "Calcular": duas causas — o `letter-spacing` adicionava espaço também depois da última letra, descentralizando visualmente o texto; e a quebra de linha/indentação dentro da tag `<button>` no HTML podia deixar espaços em branco nas bordas do texto em alguns navegadores. Ambas foram removidas.
* Link "Não sei meu CEP" com visual mais intencional (centralizado, cor da marca, sem parecer um elemento solto/quebrado).

= 2.3.1 =
* Mensagem mais precisa quando nenhuma taxa de frete é retornada: se a causa real for o CEP não existir em nenhuma das APIs consultadas, o cliente agora vê "CEP não encontrado" em vez da mensagem genérica "nenhuma opção de frete disponível", que escondia a causa real.
* Documentadas no README as duas situações mais comuns de "frete não aparece": produto sem peso cadastrado (rejeitado silenciosamente por transportadoras como Jadlog/Azul Ecommerce) e CEP inexistente.

= 2.3.0 =
* Novo diagnóstico: quando a Zona de Entrega tem métodos de frete habilitados que não retornam taxa (sem erro, apenas sem preço — comum com Melhor Envio/Frenet quando uma transportadora específica não atende ao destino ou o produto não tem peso cadastrado), o log agora registra exatamente quais métodos ficaram "silenciosos" e, quando aplicável, aponta o(s) produto(s) sem peso cadastrado como possível causa.

= 2.2.2 =
* Estendida a correção de compatibilidade com o Melhor Envio (2.2.1) para os simuladores de **Carrinho e Checkout**: o mesmo problema de fundo (o Melhor Envio não conseguir ler os produtos corretamente em determinados cenários) também podia acontecer ali, mas de forma silenciosa — retornando "Nenhuma opção de frete disponível" em vez de um erro. Agora cada item do pacote também recebe os dados formatados como rede de segurança, sem alterar o comportamento para quem já funcionava normalmente.

= 2.2.1 =
* Corrigida a causa raiz real do erro "Uma das transportadoras configuradas não respondeu corretamente" no simulador de frete da **página de produto** com o plugin oficial do Melhor Envio: o pacote simulado (fora do carrinho real) não incluía a flag `product_page_calculation` nem os dados do produto no formato interno que o Melhor Envio espera nesse cenário. Isso fazia o cálculo do Melhor Envio tentar ler do carrinho real — que funcionava "por acidente" quando havia algo no carrinho da sessão (ex.: durante testes logado), mas falhava para qualquer visitante com o carrinho vazio. Agora o pacote é montado no formato que o próprio Melhor Envio usa em sua calculadora oficial de página de produto.
* O ajuste é aplicado de forma defensiva (só age se o plugin do Melhor Envio estiver de fato instalado) e não afeta os simuladores de carrinho e checkout, que já usavam o carrinho real e não eram afetados por este bug.

= 2.2.0 =
* Corrigido bug crítico: a simulação de frete (produto, carrinho e checkout) falhava com mensagem de erro apenas para visitantes não logados, enquanto funcionava normalmente para administradores/usuários logados — em lojas usando Melhor Envio, Frenet ou outras transportadoras de terceiros. Causa: nossas rotas AJAX passam por `admin-ajax.php`, que não garante o carrinho/sessão/cliente do WooCommerce (`WC()->cart`, `WC()->session`, `WC()->customer`) totalmente inicializados para um visitante anônimo sem sessão prévia — o que fazia integrações de frete de terceiros lançarem exceção. Agora o plugin chama `wc_load_cart()` (função oficial do WooCommerce para esse cenário) no início de cada rota AJAX de frete, garantindo esses objetos disponíveis para qualquer visitante, logado ou não.
* Corrigido: ao selecionar um frete simulado no checkout usando um CEP diferente do que já estava preenchido no formulário de endereço, o WooCommerce recalculava o pedido usando o CEP antigo do formulário (não o CEP selecionado), fazendo o valor do frete divergir do que foi escolhido. Agora, ao selecionar, o CEP da simulação é sincronizado com os campos reais de cobrança/entrega antes do recálculo.

= 2.1.0 =
* Nova funcionalidade: cada opção de frete simulada no **Carrinho** e no **Checkout** agora tem um botão "Selecionar" — clicar nele aplica de verdade aquele método de frete ao pedido (usando o mesmo mecanismo nativo de sessão do WooCommerce que os botões de rádio de frete usam), em vez de a simulação ser apenas informativa. O CEP e o método são sempre revalidados no servidor no momento da seleção.
* No checkout, selecionar um frete simulado atualiza automaticamente a etapa "Método de Entrega" nativa (via evento `update_checkout`); no carrinho, a página é atualizada para refletir a nova escolha nos totais.
* A caixa da página de produto permanece apenas informativa, já que o item ainda não está no carrinho nesse momento.
* Novas mensagens configuráveis: texto do botão "Selecionar", "Aplicando frete..." e "Frete selecionado com sucesso!".

= 2.0.1 =
* Atualizada a compatibilidade declarada para WordPress até a versão 7.0.3.
* Corrigido: em temas com estilos globais fortes para campos e botões, o visual do campo de CEP e do botão "Calcular" das caixas de frete era sobrescrito pelo tema (borda, placeholder e cor do botão inconsistentes). O CSS do plugin agora tem prioridade garantida sobre os estilos do tema.
* Corrigido o alinhamento vertical do texto do botão "Calcular", que podia aparecer descentralizado dependendo do tema.
* Removida a classe genérica `button` do botão "Calcular", que estava puxando o estilo padrão do tema/WooCommerce.

= 2.0.0 =
Reformulação completa do plugin, unificando todas as evoluções feitas sobre a base da versão 1.5:

**Arquitetura**
* Reescrita modular orientada a objetos: `autocep.php` (bootstrap), `class-autocep-admin.php` (painel), `class-autocep-api.php` (busca de CEP), `class-autocep-shipping.php` (frete), `class-autocep-logger.php` (diagnóstico).
* Painel administrativo próprio, com abas: Geral & Busca de CEP, Frete na Página de Produto, Frete no Carrinho, Frete no Checkout, Mensagens & UX, Aparência, Diagnóstico e Logs.

**Busca de CEP**
* Cascata de três APIs (ViaCEP, BrasilAPI, ApiCEP) com prioridade configurável e fallback automático.
* Cache via Transients API, com duração configurável e limpeza manual.
* Aviso para CEPs sem rua cadastrada, e mensagem de erro visível para CEP inválido/não encontrado.
* Compatibilidade ampla com checkouts de terceiros via observação automática de mudanças no formulário.
* Autocomplete do navegador opcional nos campos de endereço.

**Frete**
* Três simuladores independentes: produto, carrinho e checkout — todos consultando as Zonas de Entrega do WooCommerce em tempo real, com suporte a produtos variáveis, cupons aplicados e prazo de entrega.
* Compatibilidade com métodos de frete de terceiros (Melhor Envio, Frenet e outros), com tratamento robusto de falhas e desativação automática da caixa nativa do Melhor Envio para evitar duplicidade visual.
* Compatibilidade retroativa com o shortcode `[shipping_calculator_on_product_page]` do plugin de calculadora de frete anterior.

**Aparência**
* Redesenho das caixas de frete: visual compacto e moderno, cores personalizáveis via seletor nativo do WordPress.

**Diagnóstico**
* Verificação de status em tempo real das APIs de CEP e log de falhas.

= 1.5 =
* Versão legada: autocompletar endereço no checkout via API OpenCEP.

Obrigado por usar o **AutoCEP**.

== Upgrade Notice ==

= 2.48 =
Versão grande: Checkout Moderno em etapas, tabela de frete, pagamento na entrega, sugestões de endereço, funil de conversão e correções. Faça backup antes de atualizar e desative o MSPW Pro, se usar (o AutoCEP já inclui essas funções).
