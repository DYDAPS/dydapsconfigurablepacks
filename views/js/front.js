(function () {
  'use strict';

  /**
   * Send an encoded POST request to the module AJAX endpoint.
   *
   * @param {string} url
   * @param {Object.<string, string|number>} data
   * @returns {Promise<Object>}
   */
  function request(url, data) {
    var body = new URLSearchParams(data);
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-id-product][data-ajax-url]').forEach(function (root) {
      var url = root.getAttribute('data-ajax-url');
      var idProduct = root.getAttribute('data-id-product');
      var body = root.querySelector('[data-pack-components]');
      var message = root.querySelector('[data-pack-message]');
      var summary = root.querySelector('[data-pack-summary]');
      var labelAvailable = root.getAttribute('data-label-available') || 'Available';
      var labelUnavailable = root.getAttribute('data-label-unavailable') || 'Unavailable';
      var labelEstimatedTotal = root.getAttribute('data-label-estimated-total') || 'Estimated components total:';

      // Load selectable components asynchronously so the product page can be
      // cached independently from the current pack definition.
      request(url, {action: 'describe', id_product: idProduct}).then(function (payload) {
        if (!payload.ok) {
          message.textContent = payload.error || '';
          return;
        }
        body.innerHTML = '';
        payload.components.forEach(function (component) {
          var wrapper = document.createElement('div');
          wrapper.className = 'dydaps-pack-configurator__component';
          wrapper.setAttribute('data-component', component.id_component);
          var title = document.createElement('h3');
          title.textContent = component.name;
          wrapper.appendChild(title);
          var select = document.createElement('select');
          select.setAttribute('data-pack-selection', component.id_component);
          component.products.forEach(function (product) {
            var option = document.createElement('option');
            option.value = product.id_product + ':' + product.id_product_attribute;
            option.selected = parseInt(product.is_default || 0, 10) === 1;
            option.textContent = product.name
              + (product.attributes_text ? ' - ' + product.attributes_text : '')
              + (product.reference ? ' (' + product.reference + ')' : '')
              + ' - ' + (product.available ? labelAvailable : labelUnavailable)
              + ' - ' + Number(product.impact_tax_incl || 0).toFixed(2);
            option.setAttribute('data-available', product.available ? '1' : '0');
            option.setAttribute('data-impact', product.impact_tax_incl || 0);
            select.appendChild(option);
          });
          wrapper.appendChild(select);
          if (parseInt(component.max_quantity || component.quantity || 1, 10) > parseInt(component.min_quantity || component.quantity || 1, 10)) {
            var qty = document.createElement('input');
            qty.type = 'number';
            qty.min = component.min_quantity || 1;
            qty.max = component.max_quantity || component.quantity || 1;
            qty.value = component.quantity || component.min_quantity || 1;
            qty.setAttribute('data-pack-component-quantity', component.id_component);
            wrapper.appendChild(qty);
          }
          body.appendChild(wrapper);
        });
        updateSummary();
      });

      function updateSummary() {
        var total = 0;
        root.querySelectorAll('[data-pack-selection]').forEach(function (select) {
          var option = select.options[select.selectedIndex];
          var idComponent = select.getAttribute('data-pack-selection');
          var qtyInput = root.querySelector('[data-pack-component-quantity="' + idComponent + '"]');
          var qty = qtyInput ? parseInt(qtyInput.value || '1', 10) : 1;
          total += Number(option ? option.getAttribute('data-impact') : 0) * qty;
        });
        if (summary) {
          summary.textContent = total > 0 ? (labelEstimatedTotal + ' ' + total.toFixed(2)) : '';
        }
      }

      body.addEventListener('change', updateSummary);

      var add = root.querySelector('[data-pack-add]');
      add.addEventListener('click', function () {
        var components = [];

        // The server remains the source of truth for validation; this client
        // only serializes the current select state into the expected payload.
        root.querySelectorAll('[data-pack-selection]').forEach(function (select) {
          var parts = String(select.value).split(':');
          components.push({
            id_component: parseInt(select.getAttribute('data-pack-selection'), 10),
            id_product: parseInt(parts[0], 10),
            id_product_attribute: parseInt(parts[1] || '0', 10),
            quantity: root.querySelector('[data-pack-component-quantity="' + select.getAttribute('data-pack-selection') + '"]')
              ? parseInt(root.querySelector('[data-pack-component-quantity="' + select.getAttribute('data-pack-selection') + '"]').value || '1', 10)
              : 1
          });
        });
        request(url, {action: 'add', id_product: idProduct, quantity: 1, configuration: JSON.stringify({components: components})}).then(function (payload) {
          message.textContent = payload.ok ? '' : (payload.error || '');
          if (payload.ok) {
            window.location.reload();
          }
        });
      });
    });
  });
}());
