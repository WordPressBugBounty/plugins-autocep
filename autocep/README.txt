=== AutoCEP ===
Contributors: wandersoncesar
Donate link: https://tesw.com.br/
Tags: woocommerce, checkout, cep, endereço, frete
Requires at least: 5.0
Tested up to: 7.0.3
Requires PHP: 7.4
Stable tag: 2.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Autocompletar CEP no checkout do WooCommerce e simular frete em tempo real — produto, carrinho e checkout — com múltiplas APIs, cache, fallback automático e compatibilidade com Melhor Envio, Frenet, Fluid Checkout e CartFlows.

== Descrição ==

https://youtu.be/CygVGP-LUJg

**AutoCEP** resolve dois problemas clássicos de qualquer loja WooCommerce: fazer o cliente **preencher o endereço automaticamente ao digitar o CEP** e **mostrar o valor do frete antes de chegar ao checkout** — na página do produto, no carrinho e no próprio checkout.

Se você procurou por *"autocompletar CEP no checkout WooCommerce"*, *"simular frete WooCommerce"*, *"calculadora de frete na página de produto"* ou *"preencher endereço automático WooCommerce"*, é exatamente isso que o AutoCEP faz — e sem travar seu checkout, seja ele o padrão do WooCommerce, o **Fluid Checkout**, o **CartFlows** ou um checkout em etapas feito no **Elementor Pro**.

O comportamento clássico do plugin (autocompletar endereço no checkout ao digitar o CEP) continua ativo por padrão assim que você instala — nenhuma configuração é necessária para isso. Todo o resto é opcional e fica no painel **AutoCEP** do WordPress.

= Por que instalar o AutoCEP =

* Reduz abandono de carrinho: o cliente vê o valor do frete **antes** de chegar ao checkout, na própria página do produto ou no carrinho.
* Agiliza o preenchimento do endereço no checkout, reduzindo erros de digitação e abandono por formulário longo.
* Funciona com o método de frete que sua loja já usa — Frete Fixo, Frete Grátis, retirada local, **Melhor Envio**, **Frenet** ou qualquer outro registrado como Zona de Entrega do WooCommerce — sem precisar reconfigurar nada.
* Não depende de nenhuma API paga: usa ViaCEP, BrasilAPI e ApiCEP, com cache e fallback automático entre elas.

= Autocompletar de Endereço por CEP =
* Preenche Rua, Bairro, Cidade e Estado automaticamente a partir do CEP, na Cobrança e/ou na Entrega — cada uma pode ser ligada/desligada separadamente.
* Consulta em cascata três APIs (ViaCEP, BrasilAPI e ApiCEP): se uma falhar ou não encontrar o CEP, a próxima é consultada automaticamente.
* Cache de CEPs já consultados, com duração configurável e botão de limpeza manual — menos requisições externas, resposta mais rápida para o cliente.
* Aviso automático quando o CEP é válido mas não tem rua específica cadastrada (comum em cidades pequenas), em vez de deixar o campo vazio sem explicação.
* Mensagem de erro visível para CEP inválido ou não encontrado.
* Sugestões automáticas do navegador (autocomplete) nos campos de endereço, opcional.
* **Compatível com qualquer checkout construído sobre o formulário padrão do WooCommerce** — incluindo Fluid Checkout, CartFlows e Elementor Pro — através de detecção automática de mudanças no formulário, sem depender de eventos específicos de cada plugin de checkout.

= Simulador de Frete: Produto, Carrinho e Checkout =
Três caixas de simulação de frete independentes, cada uma com posição e título configuráveis:

* **Simular frete na página do produto**, considerando (opcionalmente) a quantidade e a variação selecionadas.
* **Simular frete no carrinho de compras**, com os itens já adicionados.
* **Simular frete no checkout**, cálculo rápido só com o CEP.

Todas consultam as **Zonas de Entrega do WooCommerce** em tempo real, reconhecendo automaticamente qualquer transportadora já configurada — incluindo **Melhor Envio** e **Frenet** — sem nenhuma integração adicional.

= Personalização Visual =
Cores do campo de CEP e do botão "Calcular" (fundo, borda, texto e hover) personalizáveis com o seletor de cores nativo do WordPress, para combinar com a identidade visual da loja.

= Diagnóstico e Logs =
Status em tempo real (online/offline) de cada API de CEP e log das últimas falhas — incluindo falhas de métodos de frete de terceiros, como Melhor Envio ou Frenet, quando não retornam nenhuma taxa.

== Instalação ==

1. Faça o upload da pasta `autocep` para `/wp-content/plugins/` (ou envie o `.zip` diretamente em **Plugins > Adicionar Novo > Enviar Plugin**).
2. Ative o plugin no menu **Plugins** do WordPress.
3. Acesse o menu **AutoCEP** no painel administrativo — o autocompletar de CEP no checkout já estará funcionando; as demais abas permitem ativar o que fizer sentido para a sua loja.

**Atualizando de uma versão anterior**: basta substituir os arquivos do plugin pelos desta versão (ou desativar e reativar depois de sobrescrever). As configurações já salvas são preservadas.

== Como usar cada aba do painel ==

**Geral & Busca de CEP** — liga/desliga o autocompletar de Cobrança e Entrega no checkout, escolhe quais APIs de CEP ficam ativas e em qual ordem de prioridade, mapeia o campo de bairro, ativa o foco automático, define a duração do cache e o autocomplete do navegador.

**Frete na Página de Produto** — ativa a caixa de frete no produto, escolhe a posição na página (ou "Manual — via shortcode" para posicionar com `[shipping_calculator_on_product_page]` dentro da descrição do produto), se deve considerar a quantidade selecionada, e o título da caixa.

**Frete no Carrinho** — mesma ideia, para a página do carrinho de compras.

**Frete no Checkout** — mesma ideia, para o checkout.

**Mensagens & UX** — edita todos os textos exibidos ao cliente e o comportamento do botão "Finalizar Compra".

**Aparência** — personaliza as cores das caixas de frete.

**Diagnóstico e Logs** — verifica o status das APIs de CEP em tempo real e consulta o histórico de falhas.

== Como o simulador de frete se relaciona com o frete real do pedido ==

* **Na página do produto**: a caixa é **informativa** — mostra ao cliente, antecipadamente, quais métodos e valores estariam disponíveis para o CEP digitado. Como o item ainda nem está no carrinho, não há "seleção" possível ali; é só para ajudar na decisão de compra.
* **No carrinho e no checkout**: cada opção de frete simulada tem um botão **"Selecionar"** — clicar nele aplica de verdade aquele método ao pedido, usando o mesmo mecanismo de sessão que o WooCommerce usa nos próprios botões de rádio de frete. Ou seja, não é cosmético: a partir do clique, esse é o frete escolhido para o pedido, e a etapa "Método de Entrega" do checkout (ou os totais do carrinho) refletem essa escolha automaticamente.

O CEP e o método enviados no clique de "Selecionar" são sempre revalidados no servidor no momento da seleção — o plugin nunca aplica cegamente o que o navegador envia.

== Segurança ==

Pontos de segurança implementados no plugin, para quem for avaliar antes de instalar:

* **Nonces em toda ação AJAX**: cada requisição (busca de CEP, cálculo de frete, limpeza de cache, verificação de status, limpeza de logs) verifica uma nonce própria (`check_ajax_referer`) antes de processar qualquer coisa.
* **Verificação de capacidade** (`current_user_can('manage_options')`) em todas as ações administrativas.
* **Sanitização e validação de entrada** em todo dado recebido do cliente — CEPs são validados por formato antes de qualquer consulta ou cálculo.
* **Consultas ao banco de dados preparadas** (`$wpdb->prepare`) em toda operação com dados variáveis.
* **Escape de saída** (`esc_html`, `esc_attr`, `esc_url` etc.) em todo HTML gerado dinamicamente.
* **Nenhuma execução de código dinâmico** (sem `eval`, sem `create_function`, sem inclusão de arquivos por caminho vindo do cliente).
* **Requisições externas restritas** às APIs de CEP documentadas neste README e aos métodos de frete já configurados pela própria loja — o plugin não envia dados da loja ou dos clientes para nenhum outro destino.
* **Tratamento de falhas de terceiros isolado**: se um plugin de frete de terceiros (ex.: Melhor Envio, Frenet) lançar uma exceção ou ficar indisponível, isso é capturado e registrado no log, sem interromper o restante do checkout.

Este plugin é desenvolvido e mantido pela equipe da TESW. Como em qualquer software, recomendamos testar em ambiente de homologação antes de colocar em produção, e manter WordPress, WooCommerce e PHP sempre atualizados.

== Compatibilidade ==

* **WordPress** até a versão 7.0.3
* **WooCommerce** (checkout padrão)
* **Fluid Checkout**
* **CartFlows**
* **Elementor Pro** (checkout em etapas)
* Métodos de frete: Frete Fixo, Frete Grátis, retirada local, **Melhor Envio**, **Frenet** e qualquer outro método registrado como Zona de Entrega do WooCommerce

== Documentação de Terceiros ==

Este plugin pode consultar as seguintes APIs de CEP, conforme configuração:
* ViaCEP — https://viacep.com.br/
* BrasilAPI — https://brasilapi.com.br/
* ApiCEP — https://apicep.com/

O cálculo de frete usa exclusivamente as Zonas de Entrega já configuradas em WooCommerce > Configurações > Entrega — o AutoCEP não se conecta diretamente a nenhuma transportadora; ele lê os métodos que a própria loja já configurou (nativos do WooCommerce ou de plugins como Melhor Envio e Frenet).

== Perguntas Frequentes ==

= O autocompletar de CEP parou de funcionar depois que troquei de tema/checkout? =

O plugin observa automaticamente mudanças no formulário de checkout, então continua funcionando na maioria dos casos sem configuração extra, inclusive em checkouts de terceiros como Fluid Checkout e CartFlows. Se mesmo assim não funcionar, confira em Diagnóstico e Logs se alguma API está offline, e limpe o cache de CEPs.

= Por que aparecem duas caixas de frete na página de produto? =

Provavelmente outro plugin (ex.: Melhor Envio) também insere sua própria caixa automática. O AutoCEP desliga a do Melhor Envio automaticamente quando detecta o plugin instalado; para outras integrações, desative a exibição automática delas nas configurações do próprio plugin, ou desligue o simulador de produto do AutoCEP em vez disso.

= O simulador de frete não encontra nenhuma opção para um CEP que deveria funcionar =

Confirme que existe uma Zona de Entrega em WooCommerce > Configurações > Entrega que cubra o estado/CEP testado, com pelo menos um método habilitado. Consulte também a aba Diagnóstico e Logs — falhas de métodos de terceiros ficam registradas ali com a mensagem de erro específica.

= Apenas algumas transportadoras aparecem, mesmo com várias habilitadas na zona (ex.: Melhor Envio, Frenet) =

Isso normalmente **não é um bug** — é a própria transportadora rejeitando a cotação silenciosamente (sem erro, só sem preço) para aquele produto ou destino. A causa mais comum é o **produto estar sem peso cadastrado**: transportadoras via API (Melhor Envio, Frenet) costumam exigir peso mínimo para calcular a caixa/embalagem; Correios costuma ser mais tolerante e retornar preço mesmo sem peso definido, enquanto transportadoras privadas (Jadlog, Azul Ecommerce etc.) geralmente não.

Como resolver:

1. Vá em **Produtos**, abra o produto testado e confira a aba **Entrega** — preencha peso e, se possível, dimensões (comprimento, largura, altura).
2. Repita a simulação de frete.
3. Se ainda faltar alguma transportadora, veja a aba **AutoCEP > Diagnóstico e Logs** — a partir da versão 2.3.0, o plugin registra ali exatamente quais transportadoras habilitadas na zona não retornaram taxa, e aponta produtos sem peso cadastrado como possível causa.

= Aparece "CEP não encontrado" mesmo digitando um CEP que parece válido =

Confira se o CEP foi digitado corretamente (é comum trocar um dígito, ex. 58264-000 por 58265-000). Se o CEP realmente não existir na base dos Correios, nenhuma das três APIs consultadas (ViaCEP, BrasilAPI, ApiCEP) vai encontrá-lo — isso é esperado, não é uma falha do plugin. A aba Diagnóstico e Logs mostra uma linha "CEP não encontrado nesta API" para cada uma das três quando isso acontece, confirmando que o problema é o CEP em si.

= Clicar em uma opção de frete simulada aplica esse frete ao pedido? =

No carrinho e no checkout, sim — clicar em "Selecionar" aplica de verdade aquele método ao pedido. Na página do produto, a simulação é apenas informativa, já que o item ainda não está no carrinho. Veja a seção "Como o simulador de frete se relaciona com o frete real do pedido" acima.

== Screenshots ==

1. Geral & Busca de CEP — autocompletar de Cobrança/Entrega, campo de bairro, autocomplete do navegador e prioridade das APIs de CEP.
2. Frete na Página de Produto — ativação, posição na página e opção de considerar a quantidade selecionada.
3. Frete no Carrinho — simulador de frete configurável para a página do carrinho de compras.
4. Frete no Checkout — cálculo rápido de frete por CEP direto no checkout.
5. Mensagens & UX — todos os textos exibidos ao cliente, editáveis.
6. Aparência — personalização das cores do campo de CEP e do botão "Calcular".
7. Diagnóstico e Logs — status em tempo real das APIs de CEP e histórico de falhas.

== Ajuda e Suporte ==

Em caso de dúvidas ou problemas:
* Verifique se o CEP é válido.
* Confirme se o plugin e as APIs desejadas estão ativos em AutoCEP > Geral & Busca de CEP.
* Consulte a aba "Diagnóstico e Logs" para verificar falhas recentes.
* Teste possíveis conflitos com outros plugins.

== Créditos ==

* **Versão 2.0**: criada pela equipe TESW, com desenvolvimento principal de Wanderson Cesar.
* **Contribuição pontual na versão 1.5**: Samuel Canale.

== Licença ==

GPLv2 ou posterior.

== Desenvolvedor ==

* **Empresa**: TESW
* **Site**: https://tesw.com.br/plugins/

== Changelog ==

= 2.3.5 =
* Vídeo demonstrativo (https://youtu.be/CygVGP-LUJg) adicionado no topo da Descrição do README, no formato que o WordPress.org converte automaticamente em player incorporado.
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
