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

      var labels = {
        available: root.getAttribute('data-label-available') || 'Available',
        unavailable: root.getAttribute('data-label-unavailable') || 'Unavailable',
        include: root.getAttribute('data-label-include') || 'Include',
        quantity: root.getAttribute('data-label-quantity') || 'Quantity',
        selectVariant: root.getAttribute('data-label-select-variant') || 'Select a combination',
        customization: root.getAttribute('data-label-customization') || 'Customization',
        customizationPlaceholder: root.getAttribute('data-label-customization-placeholder') || 'Customize this component (optional)',
        estimatedTotal: root.getAttribute('data-label-estimated-total') || 'Estimated components total:',
        packPrice: root.getAttribute('data-label-pack-price') || 'Pack price:',
        loading: root.getAttribute('data-label-loading') || 'Loading pack configuration...',
        required: root.getAttribute('data-label-required') || '(required)',
        optional: root.getAttribute('data-label-optional') || '(optional)',
        fee: root.getAttribute('data-label-fee') || 'Customization fee',
        selectOption: root.getAttribute('data-label-select-option') || 'Select an option',
        customizationRequiredPlaceholder: root.getAttribute('data-label-customization-required-placeholder') || 'Customize this component (required)',
        customizationRequiredMessage: root.getAttribute('data-label-customization-required-message') || 'Please fill in the required customization before adding the pack to your cart.'
      };

      var packPricing = null;
      var feeModuleAvailable = false;
      var components = [];

      function formatPrice(value) {
        var amount = (Number(value) || 0).toFixed(2);
        return currency ? amount + ' ' + currency : amount;
      }

      function flagToBool(value) {
        return value === true || value === 1 || value === '1';
      }

      function isRequired(field) {
        return field.required === 1 || field.required === '1' || field.required === true;
      }

      function isIncluded(state) {
        return !state.optional || state.included;
      }

      function comboMatches(product, selections) {
        var match = true;
        Object.keys(selections || {}).forEach(function (gid) {
          var found = (product.attributes || []).some(function (attr) {
            return String(attr.id_attribute_group) === gid && String(attr.id_attribute) === String(selections[gid]);
          });
          if (!found) {
            match = false;
          }
        });

        return match;
      }

      function computeFeeTotal(state) {
        if (!feeModuleAvailable || !state.selected) {
          return 0;
        }
        var total = 0;
        (state.selected.customization_fields || []).forEach(function (field) {
          if (!field.fee) {
            return;
          }
          var filled = !!((state.fieldValues[field.id_customization_field] || '').trim());
          if (field.fee.apply_if_filled && !filled) {
            return;
          }
          var multiplier = field.fee.quantity_mode === 'per_customization_line' ? 1 : state.quantity;
          total += (Number(field.fee.tax_incl) || 0) * multiplier;
        });

        return total;
      }

      function updateSummary() {
        var total = packPricing !== null ? (Number(packPricing) || 0) : 0;
        components.forEach(function (state) {
          if (!isIncluded(state) || !state.selected) {
            return;
          }
          if (packPricing === null) {
            total += (Number(state.selected.impact_tax_incl) || 0) * state.quantity;
          }
          total += computeFeeTotal(state);
        });

        var label = packPricing !== null ? labels.packPrice : labels.estimatedTotal;
        summary.textContent = (packPricing !== null || total > 0)
          ? (label + ' ' + formatPrice(total))
          : '';
      }

      function renderComponent(state) {
        var wrapper = el('div', {
          'class': 'dydaps-pack-configurator__component',
          'data-component': state.id,
          'data-component-id': state.id,
          'data-component-quantity': state.quantity,
          'data-component-optional': state.optional ? '1' : '0'
        });
        wrapper.appendChild(el('h3', {}, state.name));
        state.wrapper = wrapper;

        if (state.optional) {
          var include = el('label', {'class': 'dydaps-pack-configurator__include'});
          var checkbox = el('input', {type: 'checkbox', checked: state.included ? 'checked' : ''});
          include.appendChild(checkbox);
          include.appendChild(document.createTextNode(' ' + labels.include));
          include.addEventListener('change', function () {
            state.included = checkbox.checked;
            updateSummary();
            syncAddState();
          });
          wrapper.appendChild(include);
        }

        if (!state.products.length) {
          wrapper.appendChild(el('p', {'class': 'dydaps-pack-configurator__unavailable'}, labels.unavailable));
          return wrapper;
        }

        var productIds = [];
        state.products.forEach(function (product) {
          if (productIds.indexOf(product.id_product) === -1) {
            productIds.push(product.id_product);
          }
        });
        var hasAttributes = state.products.some(function (product) {
          return product.attributes && product.attributes.length;
        });

        if (state.products.length > 1 && productIds.length === 1 && hasAttributes) {
          renderDeclinations(wrapper, state);
        } else if (state.products.length > 1) {
          renderSelectVariant(wrapper, state);
        } else {
          state.selected = state.products[0];
          wrapper.appendChild(renderProductRow(state, state.selected));
        }

        renderCustomization(wrapper, state);

        return wrapper;
      }

      function refreshComponent(state) {
        if (!state.wrapper) {
          return;
        }
        state.wrapper.innerHTML = '';
        renderComponent(state);
      }

      function renderProductRow(state, product) {
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
        if (state.showStockBadge) {
          row.appendChild(el('span', {
            'class': 'dydaps-pack-configurator__availability dydaps-pack-configurator__availability--' + (product.available ? 'ok' : 'no')
          }, product.available ? labels.available : labels.unavailable));
        }
        row.appendChild(el('span', {
          'class': 'dydaps-pack-configurator__impact'
        }, formatPrice(product.impact_tax_incl)));
        row.appendChild(el('span', {'class': 'dydaps-pack-configurator__quantity'}, labels.quantity + ': ' + state.quantity));

        return row;
      }

      function renderSelectedProductRow(wrapper, state) {
        wrapper.querySelectorAll('[data-component-selected-product]').forEach(function (row) {
          row.remove();
        });
        if (!state.selected) {
          return;
        }
        var row = renderProductRow(state, state.selected);
        row.setAttribute('data-component-selected-product', '');
        wrapper.appendChild(row);
      }

      function renderSelectVariant(wrapper, state) {
        var select = el('select', {'class': 'form-control dydaps-pack-configurator__variant-select'});
        state.products.forEach(function (product, index) {
          var option = el('option', {value: String(index)});
          option.textContent = product.name + (product.attributes_text ? ' - ' + product.attributes_text : '');
          select.appendChild(option);
        });
        select.addEventListener('change', function () {
          var product = state.products[parseInt(select.value, 10)] || null;
          if (product) {
            state.selected = product;
            renderSelectedProductRow(wrapper, state);
            renderCustomization(wrapper, state);
            updateSummary();
            syncAddState();
          }
        });

        state.selected = state.products[0];
        select.value = '0';

        var block = el('label', {'class': 'dydaps-pack-configurator__variant'});
        block.appendChild(el('span', {}, labels.selectOption));
        block.appendChild(select);
        wrapper.appendChild(block);
        renderSelectedProductRow(wrapper, state);
      }

      function buildGroups(state) {
        var groups = {};
        state.products.forEach(function (product, index) {
          (product.attributes || []).forEach(function (attr) {
            var gid = String(attr.id_attribute_group);
            var group = groups[gid] || {id: gid, name: attr.group || attr.public_group || gid, attributes: {}};
            groups[gid] = group;
            var aid = String(attr.id_attribute);
            var candidate = group.attributes[aid] || {id: aid, name: attr.name, combos: []};
            candidate.combos.push(index);
            group.attributes[aid] = candidate;
          });
        });

        return Object.keys(groups).map(function (gid) {
          return {
            id: groups[gid].id,
            name: groups[gid].name,
            attributes: Object.keys(groups[gid].attributes).map(function (aid) {
              return groups[gid].attributes[aid];
            })
          };
        });
      }

      function matchingIndexes(state) {
        var indexes = [];
        state.products.forEach(function (product, index) {
          if (comboMatches(product, state.selections)) {
            indexes.push(index);
          }
        });

        return indexes;
      }

      function hasCompleteDeclinationSelection(state) {
        return (state.groups || []).every(function (group) {
          return !!state.selections[group.id];
        });
      }

      function findSelectedDeclination(state) {
        if (!hasCompleteDeclinationSelection(state)) {
          return null;
        }
        var product = null;
        state.products.forEach(function (candidate) {
          if (!product && comboMatches(candidate, state.selections)) {
            product = candidate;
          }
        });

        return product;
      }

      function pruneInvalidDeclinationSelections(state) {
        var changed = true;
        while (changed) {
          changed = false;
          Object.keys(state.selections).forEach(function (gid) {
            var candidateSelections = {};
            Object.keys(state.selections).forEach(function (otherGid) {
              if (otherGid !== gid) {
                candidateSelections[otherGid] = state.selections[otherGid];
              }
            });
            candidateSelections[gid] = state.selections[gid];
            var valid = state.products.some(function (product) {
              return comboMatches(product, candidateSelections);
            });
            if (!valid) {
              delete state.selections[gid];
              changed = true;
            }
          });
        }
      }

      function clearDependentDeclinationSelections(state) {
        (state.groups || []).forEach(function (group) {
          if ((group.attributes || []).length > 1) {
            delete state.selections[group.id];
          }
        });
      }

      function syncSelectedDeclination(state) {
        pruneInvalidDeclinationSelections(state);
        state.selected = findSelectedDeclination(state);
        syncDeclinations(state);
        renderSelectedProductRow(state.wrapper, state);
        renderCustomization(state.wrapper, state);
        announceSelection(state);
        updateSummary();
        syncAddState();
      }

      function chipEnabled(state, groupId, candidate) {
        var other = {};
        Object.keys(state.selections).forEach(function (gid) {
          if (gid !== groupId) {
            other[gid] = state.selections[gid];
          }
        });

        return candidate.combos.some(function (index) {
          return comboMatches(state.products[index], other);
        });
      }

      function findAttribute(state, groupId, attributeId) {
        var group = null;
        (state.groups || []).forEach(function (candidate) {
          if (!group && String(candidate.id) === String(groupId)) {
            group = candidate;
          }
        });
        if (!group) {
          return null;
        }
        var attribute = null;
        (group.attributes || []).forEach(function (candidate) {
          if (!attribute && String(candidate.id) === String(attributeId)) {
            attribute = candidate;
          }
        });

        return attribute;
      }

      function syncDeclinations(state) {
        state.wrapper.querySelectorAll('.dydaps-pack-configurator__declination-chip').forEach(function (chip) {
          var groupId = chip.getAttribute('data-group');
          var attributeId = chip.getAttribute('data-attribute');
          var active = String(state.selections[groupId]) === attributeId;
          var attribute = findAttribute(state, groupId, attributeId);
          chip.classList.toggle('is-active', active);
          chip.setAttribute('aria-pressed', active ? 'true' : 'false');
          chip.disabled = !attribute || !chipEnabled(state, groupId, attribute);
        });
      }

      function announceSelection(state) {
        var region = state.wrapper.querySelector('[data-declination-live]');
        if (region) {
          region.textContent = state.selected
            ? state.selected.name + (state.selected.attributes_text ? ' - ' + state.selected.attributes_text : '')
            : '';
        }
      }

      function onDeclinationClick(state, groupId, attributeId) {
        var attribute = findAttribute(state, groupId, attributeId);
        if (!attribute) {
          return;
        }
        var tentative = {};
        Object.keys(state.selections).forEach(function (gid) {
          tentative[gid] = state.selections[gid];
        });
        if (String(tentative[groupId]) === String(attributeId)) {
          clearDependentDeclinationSelections(state);
          syncSelectedDeclination(state);

          return;
        }
        tentative[groupId] = attributeId;
        var candidate = null;
        state.products.forEach(function (product) {
          if (!candidate && comboMatches(product, tentative)) {
            candidate = product;
          }
        });
        if (!candidate) {
          return;
        }
        state.selections = tentative;
        syncSelectedDeclination(state);
      }

      function renderDeclinations(wrapper, state) {
        state.groups = buildGroups(state);
        state.selections = {};
        state.selected = null;

        var block = el('div', {'class': 'dydaps-pack-configurator__declinations'});
        var live = el('p', {
          'class': 'dydaps-sr-only',
          'data-declination-live': '',
          'aria-live': 'polite'
        });
        block.appendChild(live);

        state.groups.forEach(function (group) {
          var groupEl = el('fieldset', {'class': 'dydaps-pack-configurator__declination-group'});
          groupEl.appendChild(el('legend', {'class': 'dydaps-pack-configurator__declination-group-name'}, group.name));
          var options = el('div', {'class': 'dydaps-pack-configurator__declination-options'});
          group.attributes.forEach(function (attribute) {
            var chip = el('button', {
              type: 'button',
              'class': 'dydaps-pack-configurator__declination-chip'
                + (String(state.selections[group.id]) === attribute.id ? ' is-active' : ''),
              'data-group': group.id,
              'data-attribute': attribute.id,
              'aria-pressed': String(state.selections[group.id]) === attribute.id ? 'true' : 'false'
            }, attribute.name);
            if (!chipEnabled(state, group.id, attribute)) {
              chip.disabled = true;
            }
            chip.addEventListener('click', function () {
              onDeclinationClick(state, group.id, attribute.id);
            });
            options.appendChild(chip);
          });
          groupEl.appendChild(options);
          block.appendChild(groupEl);
        });
        wrapper.appendChild(block);
        renderSelectedProductRow(wrapper, state);
        announceSelection(state);
      }

      function renderCustomization(wrapper, state) {
        wrapper.querySelectorAll('[data-component-customization-block]').forEach(function (block) {
          block.remove();
        });

        var product = state.selected;
        if (!product) {
          return;
        }

        if (state.allowCustomization && !(product.customization_fields || []).length) {
          var legacy = el('label', {
            'class': 'dydaps-pack-configurator__customization',
            'data-component-customization-block': ''
          });
          var legacyName = labels.customization;
          if (state.customizationRequired) {
            legacyName += ' ' + labels.required;
          } else {
            legacyName += ' ' + labels.optional;
          }
          legacy.appendChild(el('span', {}, legacyName));
          var textarea = el('textarea', {
            class: 'form-control',
            rows: 2,
            placeholder: state.customizationRequired ? labels.customizationRequiredPlaceholder : labels.customizationPlaceholder
          });
          textarea.value = state.freeText;
          textarea.addEventListener('input', function () {
            state.freeText = textarea.value;
            updateSummary();
            syncAddState();
          });
          legacy.appendChild(textarea);
          wrapper.appendChild(legacy);
        }

        (product.customization_fields || []).forEach(function (field) {
          var id = String(field.id_customization_field);
          var fieldBlock = el('label', {
            'class': 'dydaps-pack-configurator__customization',
            'data-component-customization-block': ''
          });
          var name = field.name || (labels.customization + ' #' + field.id_customization_field);
          if (isRequired(field)) {
            name += ' ' + labels.required;
          }
          fieldBlock.appendChild(el('span', {}, name));
          if (field.fee && feeModuleAvailable) {
            var fee = field.fee;
            var feeLabel = labels.fee + ': +' + formatPrice(fee.tax_incl);
            if (fee.label) {
              feeLabel = fee.label + ' (+' + formatPrice(fee.tax_incl) + ')';
            }
            fieldBlock.appendChild(el('small', {'class': 'dydaps-pack-configurator__fee'}, feeLabel));
          }
          var input = field.type === 2
            ? el('textarea', {class: 'form-control', rows: 2})
            : el('input', {class: 'form-control', type: 'text'});
          input.value = state.fieldValues[id] || '';
          input.addEventListener('input', function () {
            state.fieldValues[id] = input.value;
            updateSummary();
            syncAddState();
          });
          fieldBlock.appendChild(input);
          wrapper.appendChild(fieldBlock);
        });
      }

      function hasFilledField(state) {
        return Object.keys(state.fieldValues).some(function (id) {
          return ((state.fieldValues[id] || '').trim()) !== '';
        });
      }

      function validate() {
        var errors = [];
        components.forEach(function (state) {
          if (!isIncluded(state)) {
            return;
          }
          if (!state.selected) {
            errors.push(labels.selectOption);
            return;
          }
          (state.selected.customization_fields || []).forEach(function (field) {
            if (isRequired(field) && !((state.fieldValues[String(field.id_customization_field)] || '').trim())) {
              errors.push((field.name || labels.customization) + ' ' + labels.required);
            }
          });
          if (state.customizationRequired && !((state.freeText || '').trim()) && !hasFilledField(state)) {
            errors.push(labels.customization + ' ' + labels.required);
          }
        });

        return errors;
      }

      function syncAddState() {
        var errors = validate();
        var hasComponents = components.length > 0;
        var hasCustomizationError = errors.some(function (error) {
          return error.indexOf(labels.customization) === 0;
        });
        add.disabled = !hasComponents || errors.length > 0;
        if (!hasComponents || errors.length === 0) {
          message.textContent = '';
        } else if (hasCustomizationError) {
          message.textContent = labels.customizationRequiredMessage;
        } else {
          message.textContent = errors.join('. ');
        }
      }

      function serialize() {
        var result = [];
        components.forEach(function (state) {
          if (!isIncluded(state) || !state.selected) {
            return;
          }
          var entry = {
            id_component: state.id,
            id_product: parseInt(state.selected.id_product, 10),
            id_product_attribute: parseInt(state.selected.id_product_attribute || '0', 10),
            quantity: state.quantity
          };
          var freeText = (state.freeText || '').trim();
          if (freeText) {
            entry.customization = freeText;
          }
          var allowedIds = {};
          (state.selected.customization_fields || []).forEach(function (field) {
            allowedIds[String(field.id_customization_field)] = true;
          });
          var fields = [];
          Object.keys(state.fieldValues).forEach(function (id) {
            if (!allowedIds[id]) {
              return;
            }
            var value = (state.fieldValues[id] || '').trim();
            if (value) {
              fields.push({id_customization_field: parseInt(id, 10), value: value});
            }
          });
          if (fields.length) {
            entry.customization_fields = fields;
          }
          result.push(entry);
        });

        return result;
      }

      add.disabled = true;
      body.textContent = labels.loading;

      // Load the pack composition asynchronously so the product page can be
      // cached independently from the current pack definition.
      request(url, {action: 'describe', id_product: idProduct}).then(function (payload) {
        if (!payload.ok) {
          body.textContent = '';
          message.textContent = payload.error || '';
          return;
        }
        body.innerHTML = '';
        feeModuleAvailable = !!payload.fee_module_available;
        var showStockBadge = !payload.pack
          || !Object.prototype.hasOwnProperty.call(payload.pack, 'show_stock_badge')
          || flagToBool(payload.pack.show_stock_badge);
        components = (payload.components || []).map(function (component) {
          return {
            id: parseInt(component.id_component, 10),
            name: component.name || ('Component #' + component.id_component),
            optional: flagToBool(component.optional),
            quantity: parseInt(component.quantity || 1, 10),
            allowCustomization: flagToBool(component.allow_customization),
            customizationRequired: flagToBool(component.customization_required),
            showStockBadge: showStockBadge,
            products: (component.products || []).filter(function (product) {
              return product && product.id_product;
            }),
            selected: null,
            included: true,
            freeText: '',
            fieldValues: {},
            wrapper: null,
            groups: [],
            selections: {}
          };
        });
        components.forEach(function (state) {
          body.appendChild(renderComponent(state));
        });
        packPricing = payload.pack && payload.pack.pricing_method === 'fixed'
          ? (Number(payload.pack_price_tax_incl) || 0)
          : null;
        syncAddState();
        updateSummary();
      });

      add.addEventListener('click', function () {
        var errors = validate();
        if (errors.length) {
          message.textContent = errors.join('. ');
          return;
        }

        // The server remains the source of truth for validation; this client
        // only serializes the included components into the expected payload.
        var configuration = JSON.stringify({components: serialize()});
        request(url, {
          action: 'add',
          id_product: idProduct,
          quantity: 1,
          configuration: configuration,
          csrf_token: csrfToken
        }).then(function (payload) {
          message.textContent = payload.ok ? '' : (payload.error || '');
          if (payload.ok) {
            window.location.reload();
          }
        });
      });
    });
  });
}());
