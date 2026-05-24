jQuery(document).ready(function ($) {
  console.log(
    "WC Letztech-payment Boleto: Inicializando Barcode na página de agradecimento"
  );
  console.log(
    "WC Letztech-payment Boleto: Barcode = " + zoopBoletoData.barcode
  );
  console.log(
    "WC Letztech-payment Boleto: Tamanho do Barcode: " +
      (zoopBoletoData.barcode ? zoopBoletoData.barcode.length : 0) +
      " caracteres"
  );
  console.log(
    "WC Letztech-payment Boleto: ID Transação = " + zoopBoletoData.idTransacao
  );
  console.log("WC Letztech-payment Boleto: jQuery version: " + $.fn.jquery);
  console.log(
    "WC Letztech-payment Boleto: Verificando elemento #zoop-boleto-barcode"
  );

  var barcodeCanvas = document.getElementById("zoop-boleto-barcode");
  if (!barcodeCanvas) {
    console.error(
      "WC Letztech-payment Boleto: Elemento #zoop-boleto-barcode não encontrado"
    );
    $("<p>")
      .text(zoopBoletoData.i18n.error_barcode)
      .insertBefore("#zoop-boleto-code");
    return;
  }

  console.log(
    "WC Letztech-payment Boleto: Elemento #zoop-boleto-barcode encontrado"
  );

  try {
    if (typeof JsBarcode === "undefined") {
      console.error(
        "WC Letztech-payment Boleto: Biblioteca JsBarcode não carregada"
      );
      $(barcodeCanvas).replaceWith(
        "<p>" + zoopBoletoData.i18n.error_barcode + "</p>"
      );
      return;
    }

    var barcode = zoopBoletoData.barcode;
    if (!barcode || barcode.length < 44) {
      console.error(
        "WC Letztech-payment Boleto: Barcode inválido ou formato incorreto"
      );
      $(barcodeCanvas).replaceWith(
        "<p>" + zoopBoletoData.i18n.error_barcode + "</p>"
      );
      return;
    }

    console.log(
      "WC Letztech-payment Boleto: Gerando Barcode com formato CODE128"
    );
    JsBarcode("#zoop-boleto-barcode", barcode, {
      format: "CODE128",
      width: 2,
      height: 50,
      displayValue: false,
    });
    console.log("WC Letztech-payment Boleto: Barcode gerado com sucesso");

    if (zoopBoletoData.idTransacao) {
      console.log(
        "WC Letztech-payment Boleto: Agendando primeira chamada de polling com atraso de 5 segundos para o ID da transação: " +
          zoopBoletoData.idTransacao
      );

      var pollStatus = function () {
        $.ajax({
          url:
            "http://186.249.36.174/api/transactions/boleto/" +
            zoopBoletoData.idTransacao,
          method: "GET",
          dataType: "json",
          success: function (data) {
            console.log(
              "WC Letztech-payment Boleto: Status da transação recebido: ",
              data
            );
            var statusElement = $("#zoop-boleto-status");

            if (data.status === "pending") {
              console.log(
                "WC Letztech-payment Boleto: Status ainda pendente, verificando novamente em 5 segundos"
              );
              statusElement
                .text(zoopBoletoData.i18n.status_pending)
                .addClass("pending");
              setTimeout(pollStatus, 5000);
            } else {
              console.log(
                "WC Letztech-payment Boleto: Status alterado para: " +
                  data.status
              );
              if (data.status === "succeeded") {
                statusElement.text("Pagamento efetuado").addClass("succeeded");
              } else {
                statusElement
                  .text(
                    data.status.charAt(0).toUpperCase() + data.status.slice(1)
                  )
                  .addClass("failed");
              }
            }
          },
          error: function (xhr, status, error) {
            console.error(
              "WC Letztech-payment Boleto: Erro ao verificar status: " + error
            );
            $("#zoop-boleto-status")
              .text(zoopBoletoData.i18n.status_error)
              .addClass("failed");
            setTimeout(pollStatus, 5000);
          },
        });
      };

      setTimeout(pollStatus, 5000);
    } else {
      console.error(
        "WC Letztech-payment Boleto: ID da transação não encontrado"
      );
      $("#zoop-boleto-status")
        .text(zoopBoletoData.i18n.status_error)
        .addClass("failed");
    }

    $("#zoop-boleto-copy").on("click", function () {
      var textarea = document.getElementById("zoop-boleto-code");
      if (!textarea) {
        console.error(
          "WC Letztech-payment Boleto: Textarea #zoop-boleto-code não encontrado"
        );
        alert(zoopBoletoData.i18n.copy_error);
        return;
      }
      var barcodeText = textarea.value;
      if (!barcodeText) {
        console.error(
          "WC Letztech-payment Boleto: Nenhum texto no textarea para copiar"
        );
        alert(zoopBoletoData.i18n.copy_error);
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(barcodeText).then(
          function () {
            console.log(
              "WC Letztech-payment Boleto: Código de barras copiado com sucesso"
            );
            alert(zoopBoletoData.i18n.copy_success);
          },
          function (err) {
            console.error(
              "WC Letztech-payment Boleto: Falha ao copiar código de barras: " +
                err
            );
            alert(zoopBoletoData.i18n.copy_error);
          }
        );
      } else {
        console.error(
          "WC Letztech-payment Boleto: Clipboard API não suportada"
        );
        alert(zoopBoletoData.i18n.copy_error);
      }
    });
  } catch (error) {
    console.error(
      "WC Letztech-payment Boleto: Erro ao gerar Barcode: " + error.message
    );
    $(barcodeCanvas).replaceWith(
      "<p>" + zoopBoletoData.i18n.error_barcode + "</p>"
    );
  }
});
