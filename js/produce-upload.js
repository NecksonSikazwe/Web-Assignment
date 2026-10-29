/* Vanilla JavaScript for the separate-page, temporary produce-listing workflow. */
(function () {
  'use strict';

  // Use one session key to pass a confirmed listing between the upload and product pages.
  var pendingListingKey = 'agronest-pending-produce-listing';
  var maxImageSize = 5 * 1024 * 1024;
  var imageTypes = ['image/jpeg', 'image/png', 'image/webp'];

  // Display the validated listing on the upload page and prepare its temporary handoff.
  function initUploadPage() {
    var form = document.getElementById('produce-form');
    if (!form) return;

    var fields = form.elements;
    var imageInput = fields.image;
    var imagePreview = document.getElementById('produce-image-preview');
    var previewImage = document.getElementById('produce-image-preview-img');
    var review = document.getElementById('produce-verification');
    var imageUrl = '';

    // Display a readable validation or status message beside the form.
    function showMessage(text, hasError) {
      var message = document.getElementById('produce-form-message');
      message.textContent = text;
      message.classList.toggle('produce-form-message--error', hasError);
    }

    // Clear the selected image and release its temporary browser preview URL.
    function clearImage() {
      if (imageUrl) window.URL.revokeObjectURL(imageUrl);
      imageUrl = '';
      imageInput.value = '';
      previewImage.removeAttribute('src');
      document.getElementById('listing-image').removeAttribute('src');
      imagePreview.hidden = true;
      document.getElementById('remove-produce-image').hidden = true;
    }

    // Check an image's format and size before allowing it into the preview.
    function getImageError(file) {
      if (!imageTypes.includes(file.type)) return 'Choose a JPEG, PNG, or WebP image.';
      if (file.size > maxImageSize) return 'Choose an image no larger than 5 MB.';
      return '';
    }

    // Resize the selected image before the temporary handoff so browser storage stays small.
    function prepareImageForHandoff(file, onReady, onError) {
      var reader = new FileReader();
      reader.onerror = onError;
      reader.onload = function () {
        if (typeof reader.result !== 'string') {
          onError();
          return;
        }

        var sourceImage = new Image();
        sourceImage.onerror = onError;
        sourceImage.onload = function () {
          var maxDimension = 1400;
          var scale = Math.min(1, maxDimension / Math.max(sourceImage.width, sourceImage.height));
          var canvas = document.createElement('canvas');
          canvas.width = Math.round(sourceImage.width * scale);
          canvas.height = Math.round(sourceImage.height * scale);
          var context = canvas.getContext('2d');

          if (!context) {
            onError();
            return;
          }

          // A white canvas keeps transparent PNG/WebP backgrounds clean in the JPEG handoff.
          context.fillStyle = '#ffffff';
          context.fillRect(0, 0, canvas.width, canvas.height);
          context.drawImage(sourceImage, 0, 0, canvas.width, canvas.height);
          try {
            onReady(canvas.toDataURL('image/jpeg', 0.82));
          } catch (error) {
            onError();
          }
        };
        sourceImage.src = reader.result;
      };
      reader.readAsDataURL(file);
    }

    // Show the selected photo locally; it is not uploaded to a server.
    function previewSelectedImage() {
      var file = imageInput.files[0];
      if (!file) return;

      var error = getImageError(file);
      if (error) {
        clearImage();
        showMessage(error, true);
        imageInput.focus();
        return;
      }

      if (imageUrl) window.URL.revokeObjectURL(imageUrl);
      imageUrl = window.URL.createObjectURL(file);
      previewImage.src = imageUrl;
      imagePreview.hidden = false;
      document.getElementById('remove-produce-image').hidden = false;
      showMessage('Image ready to preview.', false);
    }

    // Validate required details, numeric values, and the selected image before review.
    function validateForm() {
      var quantity = Number(fields.quantity.value);
      var price = Number(fields.price.value);
      var requiredFields = form.querySelectorAll(
        'input[required]:not([type="file"]), select[required], textarea[required]'
      );

      if (!fields.quantity.value || !Number.isFinite(quantity) || quantity <= 0) {
        showMessage('Enter a quantity greater than zero.', true);
        fields.quantity.focus();
        return false;
      }
      if (fields.price.value === '' || !Number.isFinite(price) || price < 0) {
        showMessage('Enter a valid price of zero or more.', true);
        fields.price.focus();
        return false;
      }

      for (var field of requiredFields) {
        if (!field.value.trim()) {
          var label = form.querySelector('label[for="' + field.id + '"]');
          showMessage('Complete the required field: ' + label.textContent + '.', true);
          field.focus();
          return false;
        }
      }

      var file = imageInput.files[0];
      if (!file || getImageError(file)) {
        showMessage(file ? getImageError(file) : 'Select an image for your produce.', true);
        imageInput.focus();
        return false;
      }

      var invalidField = form.querySelector('input:invalid, select:invalid, textarea:invalid');
      if (invalidField) {
        showMessage('Check the highlighted information and try again.', true);
        invalidField.focus();
        return false;
      }
      return true;
    }

    // Fill the review card with text nodes so entered text is never treated as HTML.
    function showReview() {
      var name = fields.produceName.value.trim();
      var contact = fields.contact.value.trim();
      var unit = fields.unit.value;
      var details = {
        'listing-category': fields.category.value,
        'listing-name': name,
        'listing-farmer': fields.farmerName.value.trim(),
        'listing-location': fields.location.value.trim(),
        'listing-description': fields.description.value.trim(),
        'listing-price': 'K' + Number(fields.price.value).toFixed(2) + ' / ' + unit,
        'listing-quantity': 'Available: ' + fields.quantity.value + ' ' + unit
      };

      var image = document.getElementById('listing-image');
      image.src = imageUrl;
      image.alt = name + ' produce';
      Object.keys(details).forEach(function (id) {
        document.getElementById(id).textContent = details[id];
      });

      var contactLink = document.getElementById('listing-contact');
      contactLink.textContent = 'Contact farmer: ' + contact;
      contactLink.href = 'tel:' + contact.replace(/[^\d+]/g, '');
    }

    // Send a confirmed listing to the product page for this tab's session only.
    function confirmListing() {
      if (!window.confirm(
        'Confirm these details and add the listing to this browser session? It will not be saved online.'
      )) return;

      var file = imageInput.files[0];
      var confirmButton = document.getElementById('confirm-produce-listing');
      confirmButton.disabled = true;
      showMessage('Preparing your temporary listing…', false);

      function showImagePreparationError() {
        confirmButton.disabled = false;
        showMessage('The image could not be prepared. Please select it again and retry.', true);
      }

      // Use a smaller JPEG for the tab-only handoff while leaving the original preview unchanged.
      prepareImageForHandoff(file, function (preparedImage) {
        var listing = {
          image: preparedImage,
          category: fields.category.value,
          name: fields.produceName.value.trim(),
          farmer: fields.farmerName.value.trim(),
          location: fields.location.value.trim(),
          description: fields.description.value.trim(),
          price: Number(fields.price.value),
          quantity: Number(fields.quantity.value),
          unit: fields.unit.value,
          contact: fields.contact.value.trim()
        };

        try {
          // sessionStorage keeps this browser-tab handoff temporary and avoids a backend.
          window.sessionStorage.setItem(pendingListingKey, JSON.stringify(listing));
        } catch (error) {
          confirmButton.disabled = false;
          showMessage(
            'The temporary listing could not be passed to the product page. Check browser storage settings.',
            true
          );
          return;
        }
        window.location.assign('products-services.html#featured-products');
      }, showImagePreparationError);
    }

    // Editing hides the review and returns focus to the form without losing entered details.
    document.getElementById('edit-produce-listing').addEventListener('click', function () {
      review.hidden = true;
      form.hidden = false;
      fields.produceName.focus();
    });

    // Bind image selection, removal, review, confirmation, and reset controls.
    imageInput.addEventListener('change', previewSelectedImage);
    document.getElementById('remove-produce-image').addEventListener('click', function () {
      clearImage();
      showMessage('Image removed. Select an image to continue.', false);
      imageInput.focus();
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!validateForm()) return;
      showReview();
      form.hidden = true;
      review.hidden = false;
      review.scrollIntoView({ block: 'center' });
    });
    document.getElementById('confirm-produce-listing')
      .addEventListener('click', confirmListing);
    form.addEventListener('reset', function () {
      clearImage();
      review.hidden = true;
      form.hidden = false;
      showMessage('', false);
    });
  }

  // Safely build a product card from the temporary listing received from the upload page.
  function addTemporaryProduct(listing, productGrid, template) {
    if (!listing || typeof listing !== 'object' ||
        typeof listing.image !== 'string' ||
        !/^data:image\/(jpeg|png|webp);base64,/.test(listing.image)) {
      throw new Error('The temporary listing image is not valid.');
    }

    var item = template.content.firstElementChild.cloneNode(true);
    var card = item.querySelector('.product-card');
    var category = String(listing.category);
    var filterCategory = ['Cereals', 'Legumes'].includes(category) ? 'grains' :
      ['Vegetables', 'Fruits'].includes(category) ? 'vegetables' : 'other';

    card.dataset.productId = 'temporary-' + Date.now();
    card.dataset.farmerId = 'temporary';
    card.dataset.category = filterCategory;
    card.dataset.available = 'true';
    card.querySelector('img').src = listing.image;
    card.querySelector('img').alt = String(listing.name) + ' produce';

    var details = {
      '.product-card__category': listing.category,
      '.product-card__name': listing.name,
      '.product-card__farm-name': listing.farmer,
      '.product-card__location': listing.location,
      '.product-card__description': listing.description,
      '.product-card__price strong': 'K' + Number(listing.price).toFixed(2),
      '.product-card__unit': ' / ' + listing.unit,
      '.product-card__availability': 'Available: ' + listing.quantity + ' ' + listing.unit
    };
    Object.keys(details).forEach(function (selector) {
      card.querySelector(selector).textContent = String(details[selector]);
    });

    var contactLink = card.querySelector('a');
    contactLink.classList.add('btn-contact-farmer');
    contactLink.textContent = 'Contact farmer';
    contactLink.href = 'tel:' + String(listing.contact).replace(/[^\d+]/g, '');
    productGrid.appendChild(item);
    return item;
  }

  // Read and clear one pending listing, then show a clear browser-only status message.
  function initProductsPage() {
    var productGrid = document.getElementById('product-card-grid');
    var template = document.getElementById('temporary-product-card-template');
    var successMessage = document.getElementById('produce-listing-success');
    if (!productGrid || !template || !successMessage) return;

    var savedListing;
    try {
      savedListing = window.sessionStorage.getItem(pendingListingKey);
      if (savedListing) window.sessionStorage.removeItem(pendingListingKey);
    } catch (error) {
      successMessage.textContent =
        'This browser could not access session storage, so the temporary listing was not added.';
      successMessage.hidden = false;
      return;
    }
    if (!savedListing) return;

    try {
      var listing = JSON.parse(savedListing);
      var newItem = addTemporaryProduct(listing, productGrid, template);
      successMessage.textContent =
        'Your listing has been added to this browser session only. It is not saved or shared online.';
      successMessage.hidden = false;
      newItem.scrollIntoView({ block: 'center' });
    } catch (error) {
      successMessage.textContent =
        'The temporary listing could not be displayed. Please return to the upload page and try again.';
      successMessage.hidden = false;
    }
  }

  // Initialize only the controls that exist on the current page.
  initUploadPage();
  initProductsPage();
})();
