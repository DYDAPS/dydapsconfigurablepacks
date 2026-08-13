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

  /**
   * Normalize a component into the simplified editable shape.
   *
   * @param {Object} component
   * @param {number} index
   * @returns {Object}
   */
  function normalizeComponent(component, index) {
    var product = component.products && component.products.length
      ? component.products[0]
      : component;

    return {
      id_product: parseInt(product.id_product || component.id_product || 0, 10),
      name: product.name || component.name || '',
      reference: product.reference || component.reference || '',
      quantity: Math.max(1, parseInt(component.quantity || 1, 10)),
      optional: !!component.optional,
      component_type: component.component_type || 'choice',
      position: index
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-pack-builder]').forEach(function (root) {
      var canUpdate = root.getAttribute('data-can-update') !== '0';
      var form = root.closest('form');
      var field = form ? form.querySelector('[name$="[components_json]"]') : null;
      var list = root.querySelector('[data-pack-builder-components]');
      var components = [];

      if (!field || !list) {
        return;
      }

      try {
        components = JSON.parse(field.value || '[]').map(normalizeComponent);
      } catch (error) {
        components = [];
      }

      if (!components.length) {
        components.push(normalizeComponent({}, 0));
      }

      function label(name) {
        return root.getAttribute('data-label-' + name) || name;
      }

      function serialize() {
        components.forEach(function (component, index) {
          component.position = index;
        });
        field.value = JSON.stringify(components);
      }

      function render() {
        list.innerHTML = '';
        components.forEach(function (component, index) {
          list.appendChild(renderComponent(component, index));
        });
        serialize();
      }

      function input(type, value, callback) {
        var node = el('input', {type: type, class: 'form-control'});
        node.value = value;
        node.disabled = !canUpdate;
        node.addEventListener('input', function () {
          callback(node.value);
          serialize();
        });

        return node;
      }

      function fieldBlock(title, control) {
        var block = el('label', {'class': 'dydaps-pack-builder__field'});
        block.appendChild(el('span', {}, title));
        block.appendChild(control);

        return block;
      }

      function renderComponent(component, index) {
        var card = el('div', {'class': 'dydaps-pack-builder__component'});
        var header = el('div', {'class': 'dydaps-pack-builder__component-header'});
        header.appendChild(el('strong', {}, label('component') + ' ' + (index + 1)));

        var actions = el('div', {'class': 'btn-group'});
        [
          [label('up'), function () {
            if (index > 0) {
              components.splice(index - 1, 0, components.splice(index, 1)[0]);
              render();
            }
          }],
          [label('down'), function () {
            if (index < components.length - 1) {
              components.splice(index + 1, 0, components.splice(index, 1)[0]);
              render();
            }
          }],
          [label('remove'), function () {
            components.splice(index, 1);
            if (!components.length) {
              components.push(normalizeComponent({}, 0));
            }
            render();
          }]
        ].forEach(function (action) {
          var button = el('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary'}, action[0]);
          button.disabled = !canUpdate;
          button.addEventListener('click', action[1]);
          actions.appendChild(button);
        });
        header.appendChild(actions);
        card.appendChild(header);

        var grid = el('div', {'class': 'dydaps-pack-builder__grid'});
        grid.appendChild(renderProductPicker(component));
        grid.appendChild(fieldBlock(label('quantity'), input('number', component.quantity, function (value) {
          component.quantity = Math.max(1, parseInt(value || 1, 10));
        })));

        var optional = el('select', {'class': 'custom-select'});
        optional.disabled = !canUpdate;
        optional.appendChild(el('option', {value: '0'}, label('required')));
        optional.appendChild(el('option', {value: '1'}, label('optional')));
        optional.value = component.optional ? '1' : '0';
        optional.addEventListener('change', function () {
          component.optional = optional.value === '1';
          serialize();
        });
        grid.appendChild(fieldBlock(label('required'), optional));
        card.appendChild(grid);

        return card;
      }

      function renderProductPicker(component) {
        var wrapper = el('div', {'class': 'dydaps-pack-builder__products'});
        var selected = el('div', {'class': 'dydaps-pack-builder__selected-products'});

        if (component.id_product > 0) {
          selected.appendChild(renderSelectedProduct(component));
        } else {
          selected.appendChild(el('p', {'class': 'text-muted'}, label('no-products')));
        }
        wrapper.appendChild(selected);

        var search = el('div', {'class': 'dydaps-pack-builder__search input-group'});
        var inputNode = el('input', {type: 'search', class: 'form-control', placeholder: label('search-product')});
        var button = el('button', {type: 'button', class: 'btn btn-outline-secondary'}, label('search-product'));
        inputNode.disabled = !canUpdate;
        button.disabled = !canUpdate;
        search.appendChild(inputNode);
        search.appendChild(button);
        wrapper.appendChild(search);

        var results = el('div', {'class': 'dydaps-pack-builder__results'});
        wrapper.appendChild(results);

        button.addEventListener('click', function () {
          var query = inputNode.value.trim();
          if (query.length < 2) {
            return;
          }
          var productsUrl = root.getAttribute('data-products-url') || '';
          var separator = productsUrl.indexOf('?') === -1 ? '?' : '&';
          fetch(productsUrl + separator + 'q=' + encodeURIComponent(query), {credentials: 'same-origin'})
            .then(function (response) {
              return response.json();
            })
            .then(function (payload) {
              results.innerHTML = '';
              if (!payload.products || !payload.products.length) {
                results.appendChild(el('p', {'class': 'text-muted'}, label('no-results')));
                return;
              }
              payload.products.forEach(function (product) {
                results.appendChild(renderSearchResult(component, product, results));
              });
            });
        });

        return wrapper;
      }

      function renderSelectedProduct(component) {
        var row = el('div', {'class': 'dydaps-pack-builder__product'});
        var text = el('div');
        text.appendChild(el('strong', {}, component.name || ''));
        if (component.reference) {
          text.appendChild(el('small', {}, component.reference));
        }
        row.appendChild(text);

        var removeButton = el('button', {type: 'button', class: 'btn btn-sm btn-outline-danger'}, label('remove'));
        removeButton.disabled = !canUpdate;
        removeButton.addEventListener('click', function () {
          component.id_product = 0;
          component.name = '';
          component.reference = '';
          render();
        });
        row.appendChild(removeButton);

        return row;
      }

      function renderSearchResult(component, product, results) {
        var row = el('div', {'class': 'dydaps-pack-builder__product dydaps-pack-builder__product--result'});
        var text = el('div');
        text.appendChild(el('strong', {}, product.name));
        if (product.attributes_text) {
          text.appendChild(el('span', {}, product.attributes_text));
        }
        if (product.reference) {
          text.appendChild(el('small', {}, product.reference));
        }
        row.appendChild(text);

        var addButton = el('button', {type: 'button', class: 'btn btn-sm btn-outline-primary'}, label('add-product'));
        addButton.addEventListener('click', function () {
          component.id_product = parseInt(product.id_product, 10);
          component.name = product.name + (product.attributes_text ? ' (' + product.attributes_text + ')' : '');
          component.reference = product.reference || '';
          results.innerHTML = '';
          render();
        });
        row.appendChild(addButton);

        return row;
      }

      var addComponentButton = root.querySelector('[data-pack-builder-add-component]');
      if (addComponentButton) {
        addComponentButton.addEventListener('click', function () {
          components.push(normalizeComponent({name: label('component') + ' ' + (components.length + 1)}, components.length));
          render();
        });
      }

      render();
    });
  });
}());
