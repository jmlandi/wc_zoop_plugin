/* global jQuery, wcLetztechCleanup */
jQuery(function ($) {
  "use strict";

  const fields = wcLetztechCleanup.fields;

  function removeDuplicates() {
    fields.forEach(function (field) {
      const $all = $(
        'input[name="' +
          field +
          '"], select[name="' +
          field +
          '"], textarea[name="' +
          field +
          '"]'
      );

      if ($all.length <= 1) {
        return;
      }

      let $keep = $all
        .filter(function () {
          return $(this).val() !== "";
        })
        .first();

      if (!$keep.length) {
        $keep = $all.first();
      }

      $all.not($keep).remove();
    });
  }

  $(document.body).on("updated_checkout", function () {
    setTimeout(removeDuplicates, 100);
  });

  removeDuplicates();

  $("#order_review").on("submit", function () {
    removeDuplicates();
  });
});
