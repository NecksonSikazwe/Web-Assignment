const CART_KEY = "agroNestCart";
const checkoutForm = document.querySelector("#checkout-form");
const checkoutMessage = document.querySelector("#checkout-message");

checkoutForm.addEventListener("submit", (event) => {
    const cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];

    if (cart.length === 0) {
        event.preventDefault();
        checkoutMessage.textContent = "Your cart is empty. Please add a product before checking out.";
        return;
    }

    if (!checkoutForm.checkValidity()) {
        return;
    }

    localStorage.removeItem(CART_KEY);
});
