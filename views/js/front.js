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
      var currency = root.getAttribute('data-currency') || '';
      var body = root.querySelector('[data-pack-components]');
      var message = root.querySelector('[data-pack-message]');
      var summary = root.querySelector('[data-pack-summary]');
      var add = root.querySelector('[data-pack-add]');
      var labelAvailable = root.getAttribute('data-label-available') || 'Available';
      var labelUnavailable = root.getAttribute('data-label-unavailable') || 'Unavailable';
      var labelInclude = root.getAttribute('data-label-include') || 'Include';
      var labelQuantity = root.getAttribute('data-label-quantity') || 'Quantity';
      var labelSelectVariant = root.getAttribute('data-label-select-variant') || 'Select a combination';
      var labelCustomization = root.getAttribute('data-label-customization') || 'Customization';
      var labelCustomizationPlaceholder = root.getAttribute('data-label-customization-placeholder') || 'Customize this component (optional)';
      var labelEstimatedTotal = root.getAttribute('data-label-estimated-total') || 'Estimated components total:';
      var labelPackPrice = root.getAttribute('data-label-pack-price') || 'Pack price:';
      var labelLoading = root.getAttribute('data-label-loading') || 'Loading pack configuration...';
      var packPricing = null;

      function formatPrice(value) {
        var amount = (Number(value) || 0).toFixed(2);
        return currency ? amount + ' ' + currency : amount;
      }

      add.disabled = true;
      body.textContent = labelLoading;

      // Load the pack composition asynchronously so the product page can be
      // cached independently from the current pack definition.
      request(url, {action: 'describe', id_product: idProduct}).then(function (payload) {
        if (!payload.ok) {
          body.textContent = '';
          message.textContent = payload.error || '';
          return;
        }
        body.innerHTML = '';
        payload.components.forEach(function (component) {
          body.appendChild(renderComponent(component));
        });
        packPricing = payload.pack && payload.pack.pricing_method === 'fixed'
          ? (Number(payload.pack_price_tax_incl) || 0)
          : null;
        add.disabled = false;
        updateSummary();
      });

      function renderComponent(component) {
        var products = component.products || [];
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

        if (!products.length) {
          wrapper.appendChild(el('p', {'class': 'dydaps-pack-configurator__unavailable'}, labelUnavailable));
          return wrapper;
        }

        var selected = products[0];
        var impactNode = null;
        var options = el('div', {'class': 'dydaps-pack-configurator__options'});

        if (products.length === 1) {
          options.appendChild(renderProductRow(products[0], component));
          impactNode = options.querySelector('[data-component-impact]');
        } else {
          var select = el('select', {'class': 'form-control dydaps-pack-configurator__variant-select'});
          products.forEach(function (product) {
            var option = el('option', {value: String(product.id_product_attribute || 0)});
            option.textContent = product.name + (product.attributes_text ? ' - ' + product.attributes_text : '');
            if (product.is_default) {
              option.selected = true;
            }
            select.appendChild(option);
          });
          var selectBlock = el('label', {'class': 'dydaps-pack-configurator__variant'});
          selectBlock.appendChild(el('span', {}, labelSelectVariant));
          selectBlock.appendChild(select);
          options.appendChild(selectBlock);

          impactNode = el('span', {
            'class': 'dydaps-pack-configurator__impact',
            'data-component-impact': selected.impact_tax_incl || 0,
            'data-component-product': selected.id_product,
            'data-component-attribute': selected.id_product_attribute || 0
          }, formatPrice(selected.impact_tax_incl));
          options.appendChild(impactNode);

          select.addEventListener('change', function () {
            selected = null;
            products.forEach(function (product) {
              if (String(product.id_product_attribute || 0) === select.value) {
                selected = product;
              }
            });
            if (!selected) {
              return;
            }
            impactNode.setAttribute('data-component-impact', selected.impact_tax_incl || 0);
            impactNode.setAttribute('data-component-product', selected.id_product);
            impactNode.setAttribute('data-component-attribute', selected.id_product_attribute || 0);
            impactNode.textContent = formatPrice(selected.impact_tax_incl);
            renderCustomization(wrapper, component, selected);
            updateSummary();
          });
        }

        wrapper.appendChild(options);
        renderCustomization(wrapper, component, selected);

        return wrapper;
      }

      function renderProductRow(product, component) {
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
        }, formatPrice(product.impact_tax_incl)));
        row.appendChild(el('span', {'class': 'dydaps-pack-configurator__quantity'}, labelQuantity + ': ' + (component.quantity || 1)));

        return row;
      }

      function renderCustomization(wrapper, component, product) {
        var existing = wrapper.querySelector('[data-component-customization]');
        if (existing) {
          existing.remove();
        }
        wrapper.removeAttribute('data-component-customization-value');
        if (!component.allow_customization || !product.has_customization) {
          return;
        }
        var field = el('label', {'class': 'dydaps-pack-configurator__customization'});
        field.appendChild(el('span', {}, labelCustomization));
        var textarea = el('textarea', {class: 'form-control', rows: 2, placeholder: labelCustomizationPlaceholder});
        textarea.setAttribute('data-component-customization', '');
        textarea.addEventListener('input', function () {
          wrapper.setAttribute('data-component-customization-value', textarea.value);
          updateSummary();
        });
        field.appendChild(textarea);
        wrapper.appendChild(field);
      }

      function updateSummary() {
        var total = 0;
        if (packPricing !== null) {
          summary.textContent = labelPackPrice + ' ' + formatPrice(packPricing);
          return;
        }
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
        summary.textContent = total > 0 ? (labelEstimatedTotal + ' ' + formatPrice(total)) : '';
      }

      body.addEventListener('change', function (event) {
        if (event.target && event.target.getAttribute('data-pack-optional') !== null) {
          updateSummary();
        }
      });

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
          var entry = {
            id_component: parseInt(wrapper.getAttribute('data-component-id'), 10),
            id_product: parseInt(product.getAttribute('data-component-product'), 10),
            id_product_attribute: parseInt(product.getAttribute('data-component-attribute') || '0', 10),
            quantity: parseInt(wrapper.getAttribute('data-component-quantity') || '1', 10)
          };
          var customization = (wrapper.getAttribute('data-component-customization-value') || '').trim();
          if (customization) {
            entry.customization = customization;
          }
          components.push(entry);
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
