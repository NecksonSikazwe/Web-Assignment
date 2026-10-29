// Client-side feedback supplements the server's validation; it does not replace it.
(function () {
  "use strict";

  var password = document.getElementById("password");
  var confirmation = document.getElementById("confirm-password");
  var confirmationError = document.getElementById("confirm-password-error");
  var form = document.querySelector(".registration-form");

  function checkPasswordMatch() {
    // Keep browser validation and the accessible inline error in sync as fields change.
    var mismatch = confirmation.value !== "" && password.value !== confirmation.value;
    confirmation.setCustomValidity(mismatch ? "The passwords do not match." : "");
    confirmation.setAttribute("aria-invalid", mismatch ? "true" : "false");
    confirmationError.textContent = mismatch ? "The passwords do not match." : "";
    return !mismatch;
  }

  password.addEventListener("input", checkPasswordMatch);
  confirmation.addEventListener("input", checkPasswordMatch);

  form.addEventListener("submit", function (event) {
    if (!checkPasswordMatch()) {
      event.preventDefault();
      confirmation.reportValidity();
      confirmation.focus();
    }
  });

  document.querySelectorAll("[data-password-toggle]").forEach(function (button) {
    button.addEventListener("click", function () {
      // Each toggle controls only the password input named by its aria-controls value.
      var field = document.getElementById(button.getAttribute("aria-controls"));
      var visible = field.type === "password";
      field.type = visible ? "text" : "password";
      button.textContent = visible ? "Hide" : "Show";
      button.setAttribute("aria-pressed", visible ? "true" : "false");
      button.setAttribute(
        "aria-label",
        (visible ? "Hide " : "Show ") +
          (field.id === "password" ? "password" : "confirmation password")
      );
    });
  });
})();
