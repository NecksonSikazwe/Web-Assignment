/* ==========================================================================
   AGRO-NEST — SHARED PAGE INTERACTIONS  (js/script.js)
   Existing behavior: product category filtering and farmer enquiry dialogs.
   The homepage slider has its own file so these page interactions stay separate.
   ==========================================================================

   FUTURE INTEGRATION POINTS
   --------------------------
   - loadProducts(farmerId)      → fetch /api/products?farmer_id=
   - loadFarms()                 → fetch /api/farms
   - submitEnquiry(formData)     → POST /api/enquiries
   - filterProducts(category)    → client-side or via API query param
   - searchProducts(term)        → client-side or /api/products?search=

   ENTITIES (matches proposed backend schema)
   -------------------------------------------
   FARMER    farmer_id, name, farm_name, location, description, profile_image
   PRODUCT   product_id, farmer_id, product_name, category, description,
             price, unit, quantity, image, availability
   CUSTOMER  customer_id, name, email, phone
   ENQUIRY   message_id, farmer_id, customer_id, message, timestamp
   ========================================================================== */

(function () {
  'use strict';

  // Page-specific controls are optional; each initializer exits if its markup is absent.
  /* ========================================================
     1. PRODUCT FILTER BAR
     Filters .product-card elements by their data-category
     attribute. Prepared for future server-side filtering.
     ======================================================== */

  /**
   * Initialise the product category filter bar.
   * Binds click events to filter buttons and toggles
   * product card visibility using data-category attributes.
   *
   * Future: replace DOM show/hide with an API call
   * e.g. fetchProducts({ category: filterValue })
   */
  function initProductFilter() {
    // These controls and cards are identified by their existing semantic IDs and data attributes.
    var filterList = document.getElementById('product-filter-list');
    var cardGrid   = document.getElementById('product-card-grid');

    if (!filterList || !cardGrid) return;

    var buttons = filterList.querySelectorAll('[data-filter]');

    // Delegate clicks from the filter list so each category button uses one listener.
    filterList.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-filter]');
      if (!btn) return;

      var filter = btn.getAttribute('data-filter');

      /* Update active state */
      buttons.forEach(function (b) {
        b.classList.remove('product-filter-btn--active');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('product-filter-btn--active');
      btn.setAttribute('aria-pressed', 'true');

      /* Show / hide product cards */
      // Re-query so cards added temporarily by the produce form follow the same filter.
      cardGrid.querySelectorAll('.product-card[data-product-id]').forEach(function (card) {
        var category = card.getAttribute('data-category') || '';
        var show = (filter === 'all' || category === filter);
        /* Use the li parent for display toggling */
        var li = card.closest('li');
        if (li) {
          li.hidden = !show;
        }
      });
    });
  }

  /* ========================================================
     2. CONTACT-FARMER DIALOG
     Opens a lightweight enquiry modal when a
     "Contact farmer" button is activated.
     Populates the form with farmer/product context
     via data-* attributes.

     Future: replace form action with a fetch() call
     to POST /api/enquiries with farmer_id + message.
     ======================================================== */

  /**
   * Initialise the contact-farmer enquiry dialog.
   * Listens for [data-action="contact-farmer"] buttons
   * anywhere in the document and opens the <dialog>.
   */
  function initContactFarmerDialog() {
    // The same dialog is shared by product and farm cards on pages that include it.
    var dialog  = document.getElementById('contact-farmer-dialog');
    if (!dialog) return;

    var form       = document.getElementById('farmer-enquiry-form');
    var contextEl  = document.getElementById('dialog-context');
    var closeBtn   = document.getElementById('dialog-close');
    var cancelBtn  = document.getElementById('dialog-cancel');

    /* Open dialog when any "Contact farmer" button is clicked */
    // Read the activated card's data attributes to populate its enquiry context.
    document.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-action="contact-farmer"]');
      if (!btn) return;

      var farmerId    = btn.getAttribute('data-farmer-id')   || '';
      var farmerName  = btn.getAttribute('data-farmer-name') || 'this farmer';
      var productId   = btn.getAttribute('data-product-id')  || '';
      var productName = btn.getAttribute('data-product-name') || '';

      /* Store context on the form for future JS/API use */
      // The current form flow forwards entered details to the existing contact page.
      if (form) {
        form.setAttribute('data-target-farmer-id', farmerId);
        form.setAttribute('data-target-product-id', productId);
      }

      /* Populate the context line */
      if (contextEl) {
        if (productName) {
          contextEl.textContent =
            'Enquiring about \u201c' + productName + '\u201d from ' + farmerName + '.';
        } else {
          contextEl.textContent = 'Enquiring about ' + farmerName + '.';
        }
      }

      /* Open the dialog */
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        /* Fallback for browsers without <dialog> support */
        dialog.setAttribute('open', '');
      }
    });

    /* Close on ✕ button */
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        closeDialog(dialog);
      });
    }

    /* Close on Cancel button */
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        closeDialog(dialog);
      });
    }

    /* Close when clicking the backdrop */
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        closeDialog(dialog);
      }
    });

    /* Close on Escape key (natively handled by <dialog> but
       added here for the attribute-open fallback) */
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && dialog.hasAttribute('open')) {
        closeDialog(dialog);
      }
    });

    /* Handle form submission.
       Currently redirects to the Contact page with query params
       so the general enquiry can be picked up there.
       Future: replace with fetch('/api/enquiries', { method: 'POST', body })
    */
    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();

        var farmerId   = form.getAttribute('data-target-farmer-id')  || '';
        var productId  = form.getAttribute('data-target-product-id') || '';
        var name       = form.querySelector('#enquiry-name')  ? form.querySelector('#enquiry-name').value  : '';
        var email      = form.querySelector('#enquiry-email') ? form.querySelector('#enquiry-email').value : '';
        var message    = form.querySelector('#enquiry-message') ? form.querySelector('#enquiry-message').value : '';

        /*
         * FUTURE BACKEND INTEGRATION
         * ---------------------------
         * Replace the redirect below with a fetch() call:
         *
         * fetch('/api/enquiries', {
         *   method: 'POST',
         *   headers: { 'Content-Type': 'application/json' },
         *   body: JSON.stringify({
         *     farmer_id:  farmerId,
         *     product_id: productId,
         *     name:    name,
         *     email:   email,
         *     message: message,
         *     timestamp: new Date().toISOString()
         *   })
         * })
         * .then(function(res) { return res.json(); })
         * .then(function(data) { showSuccessMessage(data); })
         * .catch(function(err) { showErrorMessage(err); });
         */

        /* Temporary: forward to contact page */
        var contactPage = findContactPagePath();
        if (contactPage) {
          var params = new URLSearchParams({
            farmer_id:  farmerId,
            product_id: productId,
            name:       name,
            email:      email,
            message:    message
          });
          window.location.href = contactPage + '?' + params.toString();
        } else {
          closeDialog(dialog);
        }
      });
    }
  }

  /* ========================================================
     3. HELPERS
     ======================================================== */

  /**
   * Close a <dialog> element and reset the enquiry form.
   * @param {HTMLDialogElement} dialog
   */
  function closeDialog(dialog) {
    // Prefer the native dialog method and retain an attribute fallback for older browsers.
    if (typeof dialog.close === 'function') {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }

    var form = dialog.querySelector('form');
    if (form) form.reset();
  }

  /**
   * Resolve the path to contact.html relative to the
   * current page location.
   * @returns {string}
   */
  function findContactPagePath() {
    // Resolve the relative contact URL based on whether this page is inside /pages/.
    var path = window.location.pathname;
    if (path.indexOf('/pages/') !== -1) {
      return 'contact.html';
    }
    return 'pages/contact.html';
  }

  /* ========================================================
     4. FUTURE HOOKS — stubs prepared for JS expansion
     ======================================================== */

  /*
   * loadProducts(options)
   * ---------------------
   * Future: fetch product listings from the API and render
   * product cards dynamically into #product-card-grid.
   *
   * function loadProducts(options) {
   *   var params = new URLSearchParams(options);
   *   fetch('/api/products?' + params)
   *     .then(res => res.json())
   *     .then(data => renderProductCards(data.products))
   *     .catch(err => console.error('Products load failed', err));
   * }
   */

  /*
   * loadFarms(options)
   * ------------------
   * Future: fetch farm listings from the API and render
   * farm cards dynamically into #farm-card-grid.
   *
   * function loadFarms(options) {
   *   var params = new URLSearchParams(options);
   *   fetch('/api/farms?' + params)
   *     .then(res => res.json())
   *     .then(data => renderFarmCards(data.farms))
   *     .catch(err => console.error('Farms load failed', err));
   * }
   */

  /*
   * renderProductCards(products)
   * ----------------------------
   * Future: template function to create product card HTML
   * from API response and insert into #product-card-grid.
   * Uses data-product-id, data-farmer-id, data-category.
   */

  /*
   * renderFarmCards(farms)
   * ----------------------
   * Future: template function to create farm card HTML
   * from API response and insert into #farm-card-grid.
   * Uses data-farm-id, data-farmer-id, data-categories.
   */

  /* ========================================================
     5. INIT — run when DOM is ready
     ======================================================== */
  // Initialize only controls present after the document has been parsed.
  document.addEventListener('DOMContentLoaded', function () {
    initProductFilter();
    initContactFarmerDialog();
  });

}());
