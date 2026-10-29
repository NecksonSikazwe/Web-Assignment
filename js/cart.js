const CART_KEY = "agroNestCart";
const cartItems = document.querySelector(".cart-items");

function getCart() {
    return JSON.parse(localStorage.getItem(CART_KEY)) || [];
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

function formatPrice(price) {
    return `K${price.toFixed(2)}`;
}

function renderCart() {
    const cart = getCart();

    if (cart.length === 0) {
        cartItems.innerHTML = "<p>Your cart is empty. Add a product to continue.</p>";
        return;
    }

    const products = cart.map((item) => `
        <article class="cart-item">
            <h3>${item.name}</h3>
            <p>${formatPrice(item.price)} each</p>
            <label>
                Quantity
                <input class="cart-quantity" type="number" min="1" value="${item.quantity}" data-product-id="${item.id}">
            </label>
            <button type="button" class="remove-from-cart" data-product-id="${item.id}">Remove</button>
        </article>
    `).join("");

    const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    cartItems.innerHTML = `${products}<p class="cart-total">Total: ${formatPrice(total)}</p>`;
}

cartItems.addEventListener("change", (event) => {
    if (!event.target.matches(".cart-quantity")) return;

    const cart = getCart();
    const item = cart.find((product) => product.id === event.target.dataset.productId);
    item.quantity = Math.max(1, Number(event.target.value) || 1);
    saveCart(cart);
    renderCart();
});

cartItems.addEventListener("click", (event) => {
    if (!event.target.matches(".remove-from-cart")) return;

    const updatedCart = getCart().filter((item) => item.id !== event.target.dataset.productId);
    saveCart(updatedCart);
    renderCart();
});

renderCart();
