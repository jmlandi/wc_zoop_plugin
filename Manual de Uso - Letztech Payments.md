# Manual de Uso — Letztech Payments para WooCommerce

**Versão do plugin:** 1.5.0  
**Última atualização:** Maio de 2026

---

## Sumário

1. [O que é o plugin?](#1-o-que-é-o-plugin)
2. [Instalação](#2-instalação)
3. [Configuração Global](#3-configuração-global)
4. [Ativando os Métodos de Pagamento](#4-ativando-os-métodos-de-pagamento)
5. [Mapeamento SKU → Seller ID](#5-mapeamento-sku--seller-id)
6. [Seller ID por Produto](#6-seller-id-por-produto)
7. [Como a Regra de Cascade Funciona](#7-como-a-regra-de-cascade-funciona)
8. [Cartão de Crédito — Como Funciona](#8-cartão-de-crédito--como-funciona)
9. [PIX — Como Funciona](#9-pix--como-funciona)
10. [Boleto Bancário — Como Funciona](#10-boleto-bancário--como-funciona)
11. [Pagamento Recorrente — Como Funciona](#11-pagamento-recorrente--como-funciona)
12. [Verificação Manual de Status](#12-verificação-manual-de-status)
13. [Atualização Automática de Pedidos](#13-atualização-automática-de-pedidos)
14. [Webhook Letztech](#14-webhook-letztech)
15. [Perguntas Frequentes](#15-perguntas-frequentes)

---

## 1. O que é o plugin?

O **WooCommerce Letztech Gateway** conecta sua loja WooCommerce à API da Letztech, um processador de pagamentos brasileiro. Com ele, seus clientes podem pagar com:

- **Cartão de Crédito** — parcelado em até 12×, com juros configuráveis por parcela
- **PIX** — pagamento instantâneo com QR Code gerado na hora
- **Boleto Bancário** — boleto com vencimento de 7 dias
- **Pagamento Recorrente** — assinatura via cartão de crédito

---

## 2. Instalação

### 2.1 Upload do Plugin

1. Acesse o painel do WordPress e vá em **Plugins → Adicionar Novo → Enviar Plugin**
2. Selecione o arquivo `.zip` do plugin e clique em **Instalar Agora**
3. Após a instalação, clique em **Ativar Plugin**

> Alternativamente, copie a pasta `WcZoop310326` para o diretório `wp-content/plugins/` via FTP.

### 2.2 Pré-requisitos

Antes de ativar, certifique-se de que:

- O WooCommerce está instalado e ativo
- Você possui um **Seller ID** válido fornecido pela Letztech
- O servidor tem acesso à URL `http://186.249.36.174` (API da Letztech)

---

## 3. Configuração Global

Após ativar o plugin, acesse **WooCommerce → Configurações → Letztech Settings**.

### 3.1 Campos Disponíveis

#### Seller ID *(obrigatório)*
O identificador do vendedor principal fornecido pela Letztech. Todos os pagamentos usam esse ID por padrão, a menos que uma regra de produto ou SKU substitua.

```
Exemplo: abc-1234-xyz
```

#### Seller ID Split 1 *(opcional)*
ID de um segundo vendedor para receber uma parte do pagamento. Usado como regra global de split — se um produto não tiver seller específico, o split aplicado será este.

#### Porcentagem Split 1 *(opcional)*
Percentual do valor total do pedido que será enviado ao **Seller ID Split 1**. Use um número entre `0` e `100`.

```
Exemplo: 30 → 30% vai para o Split 1, 70% fica com o Seller ID principal
```

#### Valor Mínimo da Parcela (R$)
Define o valor mínimo aceito por parcela no cartão de crédito. Parcelas com valor abaixo desse limite não são exibidas ao cliente.

```
Exemplo: 10.00 → parcelas abaixo de R$ 10,00 não aparecem no checkout
```

#### Juros por Parcela (1× a 12×)
Configure a taxa de juros (em %) para cada número de parcelas. O valor `0` significa sem juros.

```
Exemplo:
  1× → 0%     (sem juros)
  2× → 1,5%
  3× → 2%
  ...
  12× → 5%
```

O plugin calcula automaticamente o valor total com juros e exibe ao cliente no checkout.

#### Status Completed como Processing
Quando marcado, pedidos aprovados pela Letztech ficam com status **"Em processamento"** em vez de **"Concluído"**. Útil para lojas que precisam confirmar manualmente o envio antes de concluir o pedido.

### 3.2 Salvando as Configurações

Após preencher todos os campos, clique em **Salvar Mudanças**.

---

## 4. Ativando os Métodos de Pagamento

Cada método de pagamento precisa ser ativado individualmente em **WooCommerce → Configurações → Pagamentos**.

1. Localize os métodos da Letztech na lista (Cartão de Crédito, PIX, Boleto, Recorrência)
2. Clique em **Gerenciar** ao lado do método desejado
3. Marque **Ativar** e configure o título e descrição que aparecerão no checkout
4. Salve

---

## 5. Mapeamento SKU → Seller ID

Este recurso permite associar SKUs de produtos a Seller IDs específicos. Quando um cliente compra um produto com SKU mapeado, o pagamento é automaticamente roteado para o Seller ID correspondente.

### 5.1 Como Configurar

1. Acesse **WooCommerce → Configurações → Letztech Settings**
2. Role até a seção **Mapeamento SKU → Seller ID**
3. Clique em **+ Adicionar Mapeamento**
4. Preencha o **SKU do Produto** e o **Seller ID** correspondente
5. Repita para cada SKU necessário
6. Clique em **Salvar Mudanças**

### 5.2 Exemplo

| SKU do Produto | Seller ID |
|---|---|
| `camiseta-vendor-a` | `seller_vendora_123` |
| `calca-vendor-b` | `seller_vendorb_456` |
| `acessorio-global` | *(sem mapeamento — usa global)* |

### 5.3 Comportamento em Carrinho Misto

Quando o cliente adiciona produtos de **diferentes sellers** no mesmo carrinho:

- O plugin calcula a proporção do valor de cada seller
- O seller com **maior valor** torna-se o seller principal
- O seller com **menor valor** é aplicado como Split 1 com percentual proporcional

```
Exemplo:
  Produto A (SKU: prod-a → Seller X): R$ 70,00
  Produto B (SKU: prod-b → Seller Y): R$ 30,00
  Total: R$ 100,00

  Resultado:
    seller_id = Seller X (70%)
    seller_id_split1 = Seller Y
    percentage_split1 = 30%
```

> **Nota:** Para carrinhos com 3 ou mais sellers diferentes, apenas os dois de maior valor são contemplados no split. Os demais são agrupados ao seller principal.

---

## 6. Seller ID por Produto

Além do mapeamento por SKU, é possível definir um Seller ID diretamente na página de edição de cada produto. Essa configuração tem **prioridade máxima**.

### 6.1 Como Configurar

1. Acesse **Produtos → Todos os Produtos** e edite o produto desejado
2. Na aba **Geral** (seção de dados do produto), localize o campo **Letztech Seller ID**
3. Preencha o Seller ID específico para esse produto
4. Salve o produto

Deixe o campo vazio para usar o mapeamento por SKU ou o Seller ID global.

---

## 7. Como a Regra de Cascade Funciona

O plugin segue uma hierarquia de prioridades para determinar qual Seller ID será usado no pagamento:

```
┌─────────────────────────────────────────────────────┐
│  MENOR PRIORIDADE                                   │
│                                                     │
│  1. Configurações Globais                           │
│     (Seller ID + Split 1 + Porcentagem Split 1)    │
│                                          ↓          │
│  2. Mapeamento por SKU                              │
│     (tabela na aba Letztech Settings)               │
│                                          ↓          │
│  3. Campo "Letztech Seller ID" no Produto           │
│     (edição individual de produto)                  │
│                                                     │
│  MAIOR PRIORIDADE                                   │
└─────────────────────────────────────────────────────┘
```

**Regra prática:**
- Se o produto tem um Seller ID configurado diretamente → usa esse Seller ID
- Se não tem, mas o SKU está na tabela de mapeamento → usa o seller mapeado
- Se nenhum dos dois → usa o Seller ID global das configurações

O Seller ID resolvido é salvo no pedido (`_letztech_resolved_seller_id`) e exibido no painel administrativo do pedido.

---

## 8. Cartão de Crédito — Como Funciona

### 8.1 Fluxo do Cliente

1. O cliente seleciona **Cartão de Crédito** no checkout
2. Preenche os dados do cartão (nome, número, validade, CVV, CEP)
3. Escolhe o número de parcelas — o plugin exibe automaticamente o valor por parcela com juros
4. Clica em **Finalizar Pedido**
5. O plugin envia a transação à API Letztech
6. Se aprovada, o pedido fica com status **Concluído** (ou **Em processamento** conforme configuração)

### 8.2 Parcelamento

As opções de parcelas são calculadas dinamicamente com base no total do carrinho e nas taxas de juros configuradas. Parcelas com valor abaixo do **Valor Mínimo da Parcela** configurado não são exibidas.

### 8.3 3DS (Autenticação de Dispositivo)

O plugin captura automaticamente informações do dispositivo do cliente (resolução de tela, fuso horário, idioma) para o processo 3DS exigido pela API Letztech.

---

## 9. PIX — Como Funciona

### 9.1 Fluxo do Cliente

1. O cliente seleciona **PIX** no checkout e clica em **Finalizar Pedido**
2. O plugin gera um QR Code e um código copia-e-cola via API Letztech
3. O cliente é redirecionado para a página de obrigado, onde vê o QR Code
4. O cliente paga pelo aplicativo do banco
5. O sistema detecta o pagamento em até 60 segundos e atualiza o pedido

### 9.2 Expiração

O QR Code PIX não tem prazo fixo configurável via API — o pagamento precisa ser efetuado enquanto o pedido estiver com status **Aguardando** no WooCommerce.

### 9.3 Verificação em Tempo Real

Na página de obrigado, o JavaScript do plugin verifica o status do pagamento a cada **10 segundos** e atualiza a tela automaticamente quando o pagamento é confirmado.

---

## 10. Boleto Bancário — Como Funciona

### 10.1 Fluxo do Cliente

1. O cliente seleciona **Boleto** no checkout
2. Preenche os dados pessoais completos: nome, CPF, data de nascimento, e-mail, telefone e endereço
3. Clica em **Finalizar Pedido**
4. O plugin gera o boleto via API e redireciona para a página de obrigado
5. O cliente vê o código de barras, pode copiá-lo e acessa o link para o PDF do boleto

### 10.2 Vencimento

O boleto é gerado com **7 dias de vencimento** a partir da data do pedido.

### 10.3 Valor

O valor é enviado à API em **centavos** (ex: R$ 150,00 → `15000`).

---

## 11. Pagamento Recorrente — Como Funciona

### 11.1 Fluxo do Cliente

1. O cliente seleciona **Pagamento Recorrente** no checkout
2. Preenche dados do cartão, dados pessoais completos e parâmetros do plano:
   - Nome do plano
   - Data de vencimento da primeira cobrança
   - Data de expiração do plano
   - Frequência (diária, semanal, mensal, anual)
   - Intervalo e duração
3. Clica em **Finalizar Pedido**
4. O plano é criado na API Letztech e o pedido é marcado como aprovado

### 11.2 Observação

A gestão das cobranças recorrentes subsequentes é feita diretamente pela Letztech. O WooCommerce registra apenas o pedido inicial.

---

## 12. Verificação Manual de Status

É possível verificar o status de um pedido diretamente pela lista de pedidos do WooCommerce.

1. Acesse **WooCommerce → Pedidos**
2. Localize o pedido com status **Pendente** ou **Aguardando** e método de pagamento Letztech
3. Clique no botão **Verificar Status Letztech** na linha do pedido
4. O plugin consulta a API e atualiza o status automaticamente

Este botão aparece apenas para pedidos de Cartão de Crédito e Boleto.

---

## 13. Atualização Automática de Pedidos

O plugin possui dois processos automáticos:

### 13.1 Cron Horário — Cartão e Boleto

A cada hora, o WordPress executa uma rotina que:
- Busca todos os pedidos de Cartão de Crédito e Boleto com status Pendente/Aguardando
- Consulta o status de cada transação na API Letztech
- Atualiza o pedido conforme a resposta

### 13.2 Cron a Cada 60 Segundos — PIX

A cada 60 segundos, o WordPress verifica todos os pedidos PIX aguardando pagamento e os atualiza automaticamente.

> **Importante:** O WordPress usa um sistema de cron baseado em visitas ao site (`wp-cron`). Se a loja receber poucas visitas, os cron jobs podem atrasar. Considere configurar um cron real no servidor para maior confiabilidade.

---

## 14. Webhook Letztech

O plugin disponibiliza um endpoint para receber notificações da Letztech em tempo real.

**URL do webhook:**
```
https://seusite.com.br/wp-json/wc_zoop/v1/webhook
```

**Configure essa URL no painel da Letztech** para que pagamentos confirmados atualizem os pedidos instantaneamente, sem depender do cron.

**Formato esperado pelo endpoint:**
```json
{
  "idTransacao": "txn_abc123",
  "status": "succeeded"
}
```

**Valores de status aceitos:**

| Status da Letztech | Status no WooCommerce |
|---|---|
| `succeeded` | Concluído ou Em processamento |
| `pending` | Pendente |
| `failed` / `request failed` | Falhou |

---

## 15. Perguntas Frequentes

**O plugin funciona com HPOS (High-Performance Order Storage) do WooCommerce?**
Sim. A partir da versão 1.5.0, o plugin é totalmente compatível com HPOS. Você pode ativar as tabelas de pedidos customizadas em WooCommerce → Configurações → Avançado → Recursos sem problemas.

---

**O checkout com Blocos do WooCommerce funciona?**
Não. O plugin funciona apenas com o checkout clássico (shortcode `[woocommerce_checkout]`). Se sua loja usa o bloco de checkout, mantenha o checkout clássico ativo.

---

**O cliente não vê o QR Code do PIX. O que pode ser?**
Verifique se os arquivos JavaScript do plugin foram carregados (`qrcode.min.js`). Isso pode ocorrer por conflito com outros plugins ou temas que bloqueiam scripts. Acesse o console do navegador (F12) e verifique erros.

---

**O boleto não está aparecendo na página de obrigado.**
Pode ser que a sessão do WooCommerce tenha expirado. O plugin tenta recuperar os dados do boleto direto do pedido. Certifique-se de que o pedido foi criado com sucesso e que o meta `_letztech_boleto_barcode` foi salvo.

---

**Como saber qual Seller ID foi usado em um pedido?**
Acesse o pedido no painel do WooCommerce. Abaixo dos dados de faturamento, será exibido: **"Letztech Seller ID usado: [seller_id]"**. O ID também fica registrado nas notas do pedido.

---

**Posso ter mais de 2 sellers em um mesmo pedido?**
A API Letztech suporta apenas 1 split por transação (seller principal + 1 split). Quando o carrinho possui produtos de 3 ou mais sellers diferentes, apenas os dois de maior valor são contemplados.

---

**O plugin desinstala os cron jobs ao ser desativado?**
Sim. Ao desativar o plugin, os cron jobs `wc_zoop_check_pending_orders` e `zoop_verifica_pagamento_pix` são removidos automaticamente.

---

*Dúvidas técnicas ou comerciais: entre em contato com a equipe Letztech.*
