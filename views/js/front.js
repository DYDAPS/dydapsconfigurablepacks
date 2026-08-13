/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    DYDAPS
 * @copyright 2007-2026 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
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

  /**
   * Create a DOM element with optional attributes and text.
   *
   * @param {string} tag
   * @param {Object.<string, string>=} attributes
   * @param {string=} text
   * @returns {HTMLElement}
   */
  function el(tag, attributes, text) {
    var node = document.createElement(tag);
    Object.keys(attributes || {}).forEach(function (name) {
      node.setAttribute(name, attributes[name]);
    });
    if (typeof text === 'string') {
      node.textContent = text;
    }

    return node;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-id-product][data-ajax-url]').forEach(function (root) {
      var url = root.getAttribute('data-ajax-url');
      var idProduct = root.getAttribute('data-id-product');
      var csrfToken = root.getAttribute('data-csrf-token') || '';
      var body = root.querySelector('[data-pack-components]');
      var message = root.querySelector('[data-pack-message]');
      var summary = root.querySelector('[data-pack-summary]');
      var labelAvailable = root.getAttribute('data-label-available') || 'Available';
      var labelUnavailable = root.getAttribute('data-label-unavailable') || 'Unavailable';
      var labelInclude = root.getAttribute('data-label-include') || 'Include';
      var labelQuantity = root.getAttribute('data-label-quantity') || 'Quantity';
      var labelEstimatedTotal = root.getAttribute('data-label-estimated-total') || 'Estimated components total:';

      // Load the pack composition asynchronously so the product page can be
      // cached independently from the current pack definition.
      request(url, {action: 'describe', id_product: idProduct}).then(function (payload) {
        if (!payload.ok) {
          message.textContent = payload.error || '';
          return;
        }
        body.innerHTML = '';
        payload.components.forEach(function (component) {
          var product = (component.products || [])[0] || null;
          var wrapper = document.createElement('div');
          wrapper.className = 'dydaps-pack-configurator__component';
          wrapper.setAttribute('data-component', component.id_component);
          wrapper.setAttribute('data-component-id', component.id_component);
          wrapper.setAttribute('data-component-quantity', component.quantity || 1);
          wrapper.setAttribute('data-component-optional', parseInt(component.optional || 0, 10) === 1 ? '1' : '0');

          var header = el('h3', {}, component.name);
          wrapper.appendChild(header);

          if (parseInt(component.optional || 0, 10) === 1) {
            var include = el('label', {'class': 'dydaps-pack-configurator__include'});
            var checkbox = el('input', {type: 'checkbox', checked: 'checked'});
            checkbox.setAttribute('data-pack-optional', component.id_component);
            include.appendChild(checkbox);
            include.appendChild(document.createTextNode(' ' + labelInclude));
            wrapper.appendChild(include);
          }

          if (!product) {
            wrapper.appendChild(el('p', {'class': 'dydaps-pack-configurator__unavailable'}, labelUnavailable));
            body.appendChild(wrapper);
            return;
          }

          var row = el('div', {'class': 'dydaps-pack-configurator__product'});
          var text = el('div', {'class': 'dydaps-pack-configurator__product-text'});
          text.appendChild(el('strong', {}, product.name));
          if (product.attributes_text) {
            text.appendChild(el('span', {}, ' - ' + product.attributes_text));
          }
          if (product.reference) {
            text.appendChild(el('small', {}, product.reference));
          }
          row.appendChild(text);
          row.appendChild(el('span', {'class': 'dydaps-pack-configurator__availability dydaps-pack-configurator__availability--' + (product.available ? 'ok' : 'no')}, product.available ? labelAvailable : labelUnavailable));
          row.appendChild(el('span', {
            'class': 'dydaps-pack-configurator__impact',
            'data-component-impact': product.impact_tax_incl || 0,
            'data-component-product': product.id_product,
            'data-component-attribute': product.id_product_attribute || 0
          }, Number(product.impact_tax_incl || 0).toFixed(2)));
          row.appendChild(el('span', {'class': 'dydaps-pack-configurator__quantity'}, labelQuantity + ': ' + (component.quantity || 1)));
          wrapper.appendChild(row);
          body.appendChild(wrapper);
        });
        updateSummary();
      });

      function updateSummary() {
        var total = 0;
        body.querySelectorAll('[data-component]').forEach(function (wrapper) {
          if (wrapper.getAttribute('data-component-optional') === '1') {
            var checkbox = body.querySelector('[data-pack-optional="' + wrapper.getAttribute('data-component-id') + '"]');
            if (checkbox && !checkbox.checked) {
              return;
            }
          }
          var product = wrapper.querySelector('[data-component-impact]');
          var quantity = parseInt(wrapper.getAttribute('data-component-quantity') || '1', 10);
          total += Number(product ? product.getAttribute('data-component-impact') : 0) * quantity;
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
        // only serializes the included components into the expected payload.
        body.querySelectorAll('[data-component]').forEach(function (wrapper) {
          if (wrapper.getAttribute('data-component-optional') === '1') {
            var checkbox = body.querySelector('[data-pack-optional="' + wrapper.getAttribute('data-component-id') + '"]');
            if (checkbox && !checkbox.checked) {
              return;
            }
          }
          var product = wrapper.querySelector('[data-component-impact]');
          if (!product) {
            return;
          }
          components.push({
            id_component: parseInt(wrapper.getAttribute('data-component-id'), 10),
            id_product: parseInt(product.getAttribute('data-component-product'), 10),
            id_product_attribute: parseInt(product.getAttribute('data-component-attribute') || '0', 10),
            quantity: parseInt(wrapper.getAttribute('data-component-quantity') || '1', 10)
          });
        });

        request(url, {action: 'add', id_product: idProduct, quantity: 1, configuration: JSON.stringify({components: components}), csrf_token: csrfToken}).then(function (payload) {
          message.textContent = payload.ok ? '' : (payload.error || '');
          if (payload.ok) {
            window.location.reload();
          }
        });
      });
    });
  });
}());
