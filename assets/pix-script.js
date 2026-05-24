jQuery(document).ready(function ($) {
  console.log(
    "WC Letztech PIX: Inicializando QR Code na página de agradecimento"
  );
  console.log("WC Letztech PIX: EMV = " + zoopPixData.emv);
  console.log(
    "WC Letztech PIX: Tamanho do EMV: " +
      (zoopPixData.emv ? zoopPixData.emv.length : 0) +
      " caracteres"
  );
  console.log("WC Letztech PIX: ID Transação = " + zoopPixData.idTransacao);
  console.log("WC Letztech PIX: jQuery version: " + $.fn.jquery);
  console.log("WC Letztech PIX: Verificando elemento #zoop-pix-qrcode");

  var qrcodeDiv = document.getElementById("zoop-pix-qrcode");
  if (!qrcodeDiv) {
    console.error("WC Letztech PIX: Elemento #zoop-pix-qrcode não encontrado");
    $("<p>").text(zoopPixData.i18n.error_qrcode).insertBefore("#zoop-pix-emv");
    // return;
  }

  console.log("WC Letztech PIX: Elemento #zoop-pix-qrcode encontrado");

  try {
    if (typeof qrcode === "undefined") {
      console.error("WC Letztech PIX: Biblioteca qrcode não carregada");
      $(qrcodeDiv).html("<p>" + zoopPixData.i18n.error_qrcode + "</p>");
      return;
    }

    var emv = zoopPixData.emv;
    if (!emv || !emv.startsWith("000201")) {
      console.error("WC Letztech PIX: EMV inválido ou formato incorreto");
      $(qrcodeDiv).html("<p>" + zoopPixData.i18n.error_qrcode + "</p>");
      // return;
    }

    console.log(
      "WC Letztech PIX: Gerando QR Code com version 40 e errorCorrectionLevel L"
    );
    var qr = qrcode(40, "L");
    qr.addData(emv);
    qr.make();
    qrcodeDiv.innerHTML = qr.createImgTag(2, 4);
    console.log("WC Letztech PIX: QR Code gerado com sucesso");

    if (zoopPixData.idTransacao) {
      console.log(
        "WC Letztech PIX: Agendando primeira chamada de polling com atraso de 5 segundos para o ID da transação: " +
          zoopPixData.idTransacao
      );

      var pollStatus = function () {
        $.ajax({
          url:
            "http://186.249.36.174/api/transactions/" + zoopPixData.idTransacao,
          method: "GET",
          dataType: "json",
          success: function (data) {
            console.log(
              "WC Letztech PIX: Status da transação recebido: ",
              data
            );
            var statusElement = $("#zoop-pix-status");

            if (data.status === "pending") {
              console.log(
                "WC Letztech PIX: Status ainda pendente, verificando novamente em 5 segundos"
              );
              statusElement.text(zoopPixData.i18n.status_pending);
              setTimeout(pollStatus, 5000);
            } else {
              console.log(
                "WC Letztech PIX: Status alterado para: " + data.status
              );
              if (data.status === "succeeded") {
                statusElement.text("Pagamento efetuado").css("color", "green");
              } else {
                statusElement
                  .text(
                    data.status.charAt(0).toUpperCase() + data.status.slice(1)
                  )
                  .css("color", "green");
              }
            }
          },
          error: function (xhr, status, error) {
            console.error(
              "WC Letztech PIX: Erro ao verificar status: " + error
            );
            $("#zoop-pix-status")
              .text(zoopPixData.i18n.status_error)
              .css("color", "red");
            setTimeout(pollStatus, 5000);
          },
        });
      };

      setTimeout(pollStatus, 5000);
    } else {
      console.error("WC Letztech PIX: ID da transação não encontrado");
      $("#zoop-pix-status")
        .text(zoopPixData.i18n.status_error)
        .css("color", "red");
    }
  } catch (error) {
    console.error("WC Letztech PIX: Erro ao gerar QR Code: " + error.message);
    $(qrcodeDiv).html("<p>" + zoopPixData.i18n.error_qrcode + "</p>");
  }

  zoopPixData.copyPixCode = function () {
    var textarea = document.getElementById("zoop-pix-emv");
    textarea.select();
    try {
      document.execCommand("copy");
      alert(zoopPixData.i18n.copy_success);
      console.log("WC Letztech PIX: Código PIX copiado");
    } catch (err) {
      console.error(
        "WC Letztech PIX: Falha ao copiar código PIX: " + err.message
      );
      alert(zoopPixData.i18n.copy_error);
    }
  };
});
