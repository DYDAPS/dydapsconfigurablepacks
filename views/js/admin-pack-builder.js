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
   * Normalize a component into the editable shape.
   *
   * A component references one selectable product, the list of combination
   * identifiers the merchant allows, and whether product customization is
   * enabled for the front-office configurator.
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
      position: index,
      allowed_combinations: Array.isArray(component.allowed_combinations)
        ? component.allowed_combinations.map(Number)
        : [],
      allow_customization: !!component.allow_customization,
      has_customization: !!component.has_customization,
      combinations: Array.isArray(component.combinations) ? component.combinations : []
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

        if (component.id_product > 0) {
          card.appendChild(renderComponentOptions(component));
        }

        return card;
      }

      function renderComponentOptions(component) {
        var box = el('div', {'class': 'dydaps-pack-builder__options'});

        if (component.combinations.length) {
          var combos = el('div', {'class': 'dydaps-pack-builder__combinations'});
          combos.appendChild(el('strong', {}, label('combinations')));

          component.combinations.forEach(function (combination) {
            var item = el('label', {'class': 'dydaps-pack-builder__combination dydaps-pack-builder__check'});
            var checkbox = el('input', {type: 'checkbox', class: 'dydaps-combination-check'});
            var id = parseInt(combination.id_product_attribute, 10);
            checkbox.checked = component.allowed_combinations.indexOf(id) !== -1;
            checkbox.disabled = !canUpdate;
            checkbox.addEventListener('change', function () {
              if (checkbox.checked) {
                if (component.allowed_combinations.indexOf(id) === -1) {
                  component.allowed_combinations.push(id);
                }
              } else {
                component.allowed_combinations = component.allowed_combinations.filter(function (allowed) {
                  return allowed !== id;
                });
              }
              serialize();
            });
            item.appendChild(checkbox);
            item.appendChild(el('span', {}, combination.name || String(id)));
            combos.appendChild(item);
          });
          box.appendChild(combos);
        }

        var custom = el('div', {'class': 'dydaps-pack-builder__customization'});
        if (component.has_customization) {
          var customItem = el('label', {'class': 'dydaps-pack-builder__check'});
          var customCheck = el('input', {type: 'checkbox'});
          customCheck.checked = component.allow_customization;
          customCheck.disabled = !canUpdate;
          customCheck.addEventListener('change', function () {
            component.allow_customization = customCheck.checked;
            serialize();
          });
          customItem.appendChild(customCheck);
          customItem.appendChild(el('span', {}, label('customization')));
          custom.appendChild(customItem);
        } else {
          custom.appendChild(el('small', {'class': 'text-muted'}, label('no-customization')));
        }
        box.appendChild(custom);

        return box;
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
              if (!response.ok) {
                throw new Error('HTTP ' + response.status);
              }
              return response.json();
            })
            .then(function (payload) {
              if (!payload || payload.ok === false) {
                throw new Error('invalid product search response');
              }
              results.innerHTML = '';
              var rows = payload.products || [];
              if (!rows.length) {
                results.appendChild(el('p', {'class': 'text-muted'}, label('no-results')));
                return;
              }
              var visible = 6;
              var renderRows = function () {
                results.innerHTML = '';
                rows.slice(0, visible).forEach(function (product) {
                  results.appendChild(renderSearchResult(component, product, results));
                });
                if (rows.length > visible) {
                  var more = el('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary'}, label('show-more') + ' (' + (rows.length - visible) + ')');
                  more.disabled = !canUpdate;
                  more.addEventListener('click', function () {
                    visible += 6;
                    renderRows();
                  });
                  results.appendChild(more);
                }
              };
              renderRows();
            })
            .catch(function () {
              results.innerHTML = '';
              results.appendChild(el('p', {'class': 'text-danger'}, label('search-error')));
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
        if (component.combinations.length) {
          text.appendChild(el(
            'small',
            {},
            label('combinations') + ': ' + component.allowed_combinations.length + ' / ' + component.combinations.length
          ));
        }
        row.appendChild(text);

        var removeButton = el('button', {type: 'button', class: 'btn btn-sm btn-outline-danger'}, label('remove'));
        removeButton.disabled = !canUpdate;
        removeButton.addEventListener('click', function () {
          component.id_product = 0;
          component.name = '';
          component.reference = '';
          component.allowed_combinations = [];
          component.combinations = [];
          component.allow_customization = false;
          component.has_customization = false;
          render();
        });
        row.appendChild(removeButton);

        return row;
      }

      function renderSearchResult(component, product, results) {
        var row = el('div', {'class': 'dydaps-pack-builder__product dydaps-pack-builder__product--result'});
        var text = el('div');
        text.appendChild(el('strong', {}, product.name));
        if (product.reference) {
          text.appendChild(el('small', {}, product.reference));
        }
        if (product.has_combinations) {
          text.appendChild(el('small', {}, product.combinations.length + ' ' + label('combinations')));
        }
        row.appendChild(text);

        var addButton = el('button', {type: 'button', class: 'btn btn-sm btn-outline-primary'}, label('add-product'));
        addButton.disabled = !canUpdate;
        addButton.addEventListener('click', function () {
          component.id_product = parseInt(product.id_product, 10);
          component.name = product.name;
          component.reference = product.reference || '';
          component.has_customization = !!product.has_customization;
          component.combinations = (product.combinations || []).map(function (combination) {
            return {
              id_product_attribute: parseInt(combination.id_product_attribute, 10),
              name: combination.name
            };
          });
          component.allowed_combinations = component.combinations.map(function (combination) {
            return combination.id_product_attribute;
          });
          component.allow_customization = component.has_customization;
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
