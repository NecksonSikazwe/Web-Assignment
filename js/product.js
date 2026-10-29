const CART_KEY = "agroNestCart";

function getCart() {
    return JSON.parse(localStorage.getItem(CART_KEY)) || [];
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

document.querySelectorAll(".add-to-cart").forEach((button) => {
    button.addEventListener("click", () => {
        const productCard = button.closest("[data-product-id]");
        const product = {
            id: productCard.dataset.productId,
            name: productCard.dataset.productName,
            price: Number(productCard.dataset.productPrice),
            quantity: 1
        };

        const cart = getCart();
        const existingProduct = cart.find((item) => item.id === product.id);

        if (existingProduct) {
            existingProduct.quantity += 1;
        } else {
            cart.push(product);
        }

        saveCart(cart);
        document.querySelector("#cart-message").textContent = `${product.name} was added to your cart.`;
    });
});
