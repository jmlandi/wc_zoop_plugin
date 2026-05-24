# WooCommerce Letztech Gateway

Plugin de pagamento para WooCommerce que integra a loja com a API Letztech, um processador de pagamentos brasileiro.

## Visão Geral

| Propriedade | Valor |
|---|---|
| **Nome** | WooCommerce Letztech Gateway |
| **Versão** | 1.5.0 |
| **Autor** | Softkuka |
| **Text Domain** | `wc-zoop-payments` |
| **Provedor** | Letztech (`http://186.249.36.174`) |

## Métodos de Pagamento

| Gateway | ID | Descrição |
|---|---|---|
| Cartão de Crédito | `zoop_credit_card` | Pagamento parcelado com juros configuráveis |
| PIX | `zoop_pix` | Pagamento instantâneo com QR Code |
| Boleto Bancário | `zoop_boleto` | Boleto com vencimento de 7 dias |
| Recorrência | `zoop_recurrence` | Plano de pagamento recorrente via cartão |

## Estrutura de Arquivos

```
wc-zoop-payments.php                                ← Bootstrap principal
includes/
  class-wc-gateway-zoop-credit-card-interest.php   ← Gateway Cartão de Crédito
  class-wc-gateway-zoop-pix.php                    ← Gateway PIX
  class-wc-gateway-zoop-boleto.php                 ← Gateway Boleto
  class-wc-gateway-zoop-recurrence.php             ← Gateway Recorrência
  class-wc-gateway-zoop-credit-card.php            ← Legado (não registrado)
assets/
  qrcode.min.js           ← Gerador de QR Code (PIX)
  pix-script.js           ← Lógica da página de obrigado (PIX)
  zoop-boleto-script.js   ← Lógica da página de obrigado (Boleto)
  checkout-cleanup.js     ← Remove campos duplicados no checkout
  jsbarcode.min.js        ← Renderizador de código de barras (Boleto)
```

## Requisitos

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+

## Compatibilidade

- **HPOS (High-Performance Order Storage):** Compatível — declarado via `FeaturesUtil` e uso exclusivo da API `$order->get_meta()` / `$order->update_meta_data()`
- **Block Checkout:** Declarado como não compatível; requer checkout clássico (shortcode)

## Instalação

1. Faça upload da pasta `WcZoop310326` para `wp-content/plugins/`
2. Ative o plugin em **WordPress → Plugins**
3. Acesse **WooCommerce → Configurações → Letztech Settings**
4. Configure o Seller ID fornecido pela Letztech
5. Ative os métodos de pagamento desejados em **WooCommerce → Configurações → Pagamentos**

## Configuração Global

Acesse **WooCommerce → Configurações → Letztech Settings**.

| Campo | Descrição |
|---|---|
| Seller ID | ID principal do vendedor na API Letztech |
| Seller ID Split 1 | ID do vendedor secundário para divisão de pagamento (global) |
| Porcentagem Split 1 | Percentual (0–100%) destinado ao Split 1 (global) |
| Valor Mínimo da Parcela | Valor mínimo por parcela no cartão de crédito |
| Juros Nx | Taxa de juros (%) por número de parcelas (1× a 12×) |
| Status Completed como Processing | Se ativado, pedidos aprovados ficam com status "Processing" |
| Mapeamento SKU → Seller ID | Tabela de regras por SKU de produto |

## Regra de Cascade — Seller ID

O plugin aplica uma **regra de cascade** para determinar qual Seller ID usar em cada pagamento:

```
Configurações globais (menor prioridade)
        ↓
Mapeamento por SKU (tabela na aba Letztech Settings)
        ↓
Campo "Letztech Seller ID" na edição do produto (maior prioridade)
```

Quando produtos de diferentes sellers estão no mesmo carrinho, o plugin calcula a proporção de cada seller com base nos totais dos itens e aplica o split automaticamente.

## Webhook

Endpoint para receber notificações da Letztech:

```
POST /wp-json/wc_zoop/v1/webhook
Body: { "idTransacao": "...", "status": "succeeded|pending|failed" }
```

## Cron Jobs

| Hook | Frequência | Descrição |
|---|---|---|
| `wc_zoop_check_pending_orders` | A cada hora | Verifica cartão/boleto pendentes |
| `zoop_verifica_pagamento_pix` | A cada 60 segundos | Verifica PIX aguardando confirmação |

Ambos são removidos automaticamente na desativação do plugin.

## Changelog

### 1.5.0
- Adicionada declaração de compatibilidade com HPOS (custom order tables)
- Substituídas todas as chamadas `get_post_meta` / `update_post_meta` de pedidos pela API `$order->get_meta()` / `$order->update_meta_data()`
- Consultas `meta_query` substituídas por `meta_key`/`meta_value`/`meta_compare` (compatibilidade HPOS)
- Adicionado hook de desativação para limpeza dos cron jobs
- Corrigida duplicidade na chamada `add_action('admin_init', 'wc_zoop_register_settings')`
- Corrigido registro duplicado da opção `wc_zoop_min_installment`
- Funções movidas para escopo global (eliminado risco de "Cannot redeclare")
- Adicionado filtro `payment_method => 'zoop_pix'` no cron de verificação PIX
- **Novo:** Mapeamento SKU → Seller ID na aba de configurações
- **Novo:** Campo "Letztech Seller ID" na edição de produto
- **Novo:** Regra de cascade para seleção do Seller ID
- **Novo:** Split automático proporcional entre sellers em carrinho misto
- **Novo:** `_letztech_resolved_seller_id` salvo no pedido para rastreabilidade

### 1.4.7
- Versão original
