=== AutoCEP ===
Contributors: wanderson100
Donate link: https://tesw.com.br/
Tags: autocep, correios, frete, frete woocommerce, calcular frete, melhor envio, frenet, preencher cep, checkout woocommerce, fluid checkout, cartflows, frete por km, entrega motoboy, barra frete gratis, previsao de entrega, growth marketing
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.46
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

O autocompletar de CEP mais rápido do mercado, com medição de latência em milissegundos, e simulação de frete em tempo real no produto, carrinho e checkout. Previsão de entrega, barra de frete grátis inteligente, frete por Km com contingência via Google Maps, retirada por proximidade, personalização visual completa e analytics — 100% gratuito, sem versões "Lite" limitadas.

== Description ==

O **AutoCEP** transforma a logística da sua loja WooCommerce: autocompleta o endereço do cliente ao digitar o CEP e simula o frete em tempo real — na página do produto, no carrinho e no checkout — eliminando as duas maiores causas de abandono de carrinho no e-commerce brasileiro: o atrito de preencher um formulário longo e a incerteza do valor do frete antes da decisão de compra.

Desenvolvido pela equipe de engenharia da TESW, em parceria estratégica com a Ritmo Digital, todos os módulos — frete por Km, Growth Marketing (previsão de entrega e barra de frete grátis), retirada em loja por proximidade, personalização visual completa e o dashboard de Analytics — vêm desbloqueados desde a instalação, sem cobrança de mensalidade e sem recursos limitados atrás de upgrade.

### Assista ao Vídeo de Apresentação e Demonstração
[Assista no YouTube](https://youtu.be/YnDT_qNIH1w)

### Documentação Oficial do Plugin
Guia completo de instalação, configuração de cada aba, integração com transportadoras e personalização visual: [tesw.com.br/documentacao-autocep](https://tesw.com.br/documentacao-autocep)

### Por que instalar o AutoCEP

* **Painel de Latência em Tempo Real (ms) e Prioridade de APIs**: o AutoCEP consulta ViaCEP, BrasilAPI e ApiCEP. Na aba "Frete e Busca de CEP", o botão "Testar CEP Agora" mede a latência de cada provedor em milissegundos, para você definir a ordem de prioridade (1ª, 2ª, 3ª escolha). Se uma API oscilar, a próxima assume automaticamente em cascata.
* **100% gratuito de verdade**: frete por Km, simulador de produto, Growth Marketing, previsão de entrega, personalização visual e dashboard de Analytics vêm todos desbloqueados, sem custo.
* **Zero divergência de valores**: o plugin não estima nem usa tabela própria — ele lê as Zonas de Entrega reais do seu WooCommerce. O valor simulado é o mesmo cobrado no checkout.
* **Fallback automático para o Google Maps**: no cálculo de frete por Km e na retirada por proximidade, se a Distance Matrix API do Google sofrer timeout ou instabilidade, o plugin recalcula por estimativa (linha reta × 1.3), sem deixar de mostrar uma opção de frete ao cliente.
* **Navegação ágil com submenus dedicados**: atalhos diretos com ícones próprios para Visão Geral, Analytics, Growth Marketing, Retirada em Loja, Integrações de Frete, Suporte Técnico e Diagnóstico.
* **Suporte integrado ao painel**: abra chamados direto do WordPress, com número sequencial (ex.: AC-000123) e botões de contato rápido (e-mail e WhatsApp) que já carregam uma mensagem pronta com o site e a versão do plugin.
* **Cache duplo de alta performance**: Cache de CEPs (dias configuráveis) e Cache de Cotações de Frete (horas configuráveis), deixando simulações repetidas quase instantâneas e reduzindo chamadas a APIs de transportadoras como Melhor Envio e Frenet.
* **Seleção real no checkout e no carrinho**: clicar em "Selecionar" aplica de verdade aquele método à sessão do WooCommerce, sempre revalidado no servidor antes de ser aplicado.
* **Compatibilidade universal com CSS de prioridade garantida**: funciona com o checkout padrão do WooCommerce, Fluid Checkout, CartFlows e Elementor Pro — as cores e tipografia configuradas no painel sobrepõem até temas com estilos globais agressivos.

### Funcionalidades Incluídas (Todas Gratuitas)

* **Autocompletar de Endereço Inteligente**: preenchimento automático de Rua, Bairro, Cidade e Estado (Cobrança e Entrega), foco automático no campo "Número", detecção dos campos de bairro mais comuns (`billing_neighborhood`, `billing_bairro`, `billing_district`) e aviso para CEPs sem rua específica cadastrada.
* **Simuladores de Frete (Produto, Carrinho e Checkout)**: cotação na página do produto (considerando quantidade e variação), no carrinho (somando os itens) e no checkout (cálculo rápido por CEP). Pode ser posicionado manualmente com o shortcode `[shipping_calculator_on_product_page]`, e desliga automaticamente a caixa nativa do Melhor Envio para não duplicar campos.
* **Growth Marketing — Previsão de Entrega e Frete Grátis Dinâmico**: datas estimadas de entrega (ex.: "Chega entre Quarta-feira (25/10) e Sexta-feira (27/10)"), com opção de contar só dias úteis, e Barra de Progresso de Frete Grátis com a tag dinâmica `{missing}` (ex.: "Faltam {missing} para você ganhar Frete Grátis!"), detectando automaticamente as regras de frete grátis já configuradas no WooCommerce.
* **Retirada em Loja por Proximidade e Frete por Km (Google Maps)**: cálculo de distância rodoviária real com fórmula de Taxa Fixa + (Km × Valor por Km), raio e valor mínimo configuráveis, e testes integrados de Status Rápido da API e Diagnóstico Detalhado de distância entre dois CEPs.
* **Integrações de Frete**: testes de conexão em tempo real com as APIs do Melhor Envio e da Frenet — o token é detectado automaticamente a partir do que já está salvo nos plugins oficiais de cada transportadora (nenhum campo para preencher), confirmando se ele é válido e se a API está no ar, sem precisar simular um pedido completo.
* **Personalização Visual Completa**: cor de fundo, borda e texto da caixa e do botão, arredondamento (nenhum/pequeno/médio/grande), tipografia (tamanho e peso da fonte) e espaçamento (compacto/normal/espaçoso), incluindo cores próprias para a barra de frete grátis em progresso e ao ser conquistada.
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

**Growth Marketing** — ativa a Previsão de Entrega e a Barra de Progresso de Frete Grátis Dinâmico.

**Retirada em Loja** — chave de API do Google Maps, cadastro de lojas físicas e configuração do Frete por Km.

**Integrações de Frete** — testa a conexão com as APIs do Melhor Envio e da Frenet.

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

Ele lê em tempo real as transportadoras já configuradas em WooCommerce > Configurações > Entrega — funciona com Frete Fixo, Frete Grátis, Retirada Local, Correios, Melhor Envio e Frenet.

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

Confira se o CEP foi digitado corretamente. Se ele realmente não existir na base dos Correios, nenhuma das três APIs (ViaCEP, BrasilAPI, ApiCEP) vai encontrá-lo — isso é esperado, não é falha do plugin.

= Clicar em uma opção de frete simulada aplica esse frete ao pedido? =

No carrinho e no checkout, sim. Na página do produto, a simulação é apenas informativa, já que o item ainda não está no carrinho.

== Compatibility ==

* **Checkouts compatíveis**: checkout padrão do WooCommerce, Fluid Checkout, CartFlows e montagens em etapas via Elementor Pro.
* **Integradores de frete**: Melhor Envio, Frenet, Correios, Frete Fixo, Frete Grátis e Retirada Local.
* **APIs de CEP**: ViaCEP, BrasilAPI e ApiCEP.
* **APIs de rota**: Google Maps Distance Matrix API (opcional, usada apenas nos módulos de Frete por Km e Retirada por Proximidade).

== Documentação de Terceiros ==

Este plugin pode consultar as seguintes APIs de CEP, conforme configuração:
* [ViaCEP](https://viacep.com.br/)
* [BrasilAPI](https://brasilapi.com.br/)
* [ApiCEP](https://apicep.com/)

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
