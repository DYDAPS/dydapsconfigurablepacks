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
   * Normalize component data loaded from the hidden field.
   *
   * @param {Object} component
   * @param {number} index
   * @returns {Object}
   */
  function normalizeComponent(component, index) {
    return {
      name: component.name || 'Component ' + (index + 1),
      position: index,
      component_type: component.component_type || 'choice',
      optional: !!component.optional,
      quantity: Math.max(1, parseInt(component.quantity || 1, 10)),
      min_quantity: Math.max(0, parseInt(component.min_quantity || 1, 10)),
      max_quantity: Math.max(1, parseInt(component.max_quantity || component.quantity || 1, 10)),
      pricing_behavior: component.pricing_behavior || 'native',
      fixed_price_tax_excl: Number(component.fixed_price_tax_excl || 0),
      discount_percent: Number(component.discount_percent || 0),
      surcharge_tax_excl: Number(component.surcharge_tax_excl || 0),
      active: parseInt(component.active || 1, 10),
      products: Array.isArray(component.products) ? component.products.map(function (product, productIndex) {
        return {
          id_product: parseInt(product.id_product || 0, 10),
          id_product_attribute: parseInt(product.id_product_attribute || 0, 10),
          name: product.name || '',
          reference: product.reference || '',
          attributes_text: product.attributes_text || '',
          image: product.image || '',
          is_default: product.is_default ? 1 : 0,
          position: productIndex,
          active: parseInt(product.active || 1, 10)
        };
      }).filter(function (product) {
        return product.id_product > 0;
      }) : []
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
        components.push(normalizeComponent({name: root.getAttribute('data-label-component') + ' 1'}, 0));
      }

      function label(name) {
        return root.getAttribute('data-label-' + name) || name;
      }

      function serialize() {
        components.forEach(function (component, index) {
          component.position = index;
          component.products.forEach(function (product, productIndex) {
            product.position = productIndex;
          });
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
        grid.appendChild(fieldBlock(label('name'), input('text', component.name, function (value) {
          component.name = value;
        })));
        grid.appendChild(fieldBlock(label('quantity'), input('number', component.quantity, function (value) {
          component.quantity = Math.max(1, parseInt(value || 1, 10));
        })));
        grid.appendChild(fieldBlock(label('min'), input('number', component.min_quantity, function (value) {
          component.min_quantity = Math.max(0, parseInt(value || 0, 10));
        })));
        grid.appendChild(fieldBlock(label('max'), input('number', component.max_quantity, function (value) {
          component.max_quantity = Math.max(1, parseInt(value || 1, 10));
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

        var pricing = el('select', {'class': 'custom-select'});
        pricing.disabled = !canUpdate;
        [
          ['native', label('native')],
          ['fixed', label('fixed')],
          ['discount_percent', label('discount')],
          ['surcharge', label('surcharge')]
        ].forEach(function (choice) {
          pricing.appendChild(el('option', {value: choice[0]}, choice[1]));
        });
        pricing.value = component.pricing_behavior;
        pricing.addEventListener('change', function () {
          component.pricing_behavior = pricing.value;
          serialize();
        });
        grid.appendChild(fieldBlock(label('pricing'), pricing));
        grid.appendChild(fieldBlock(label('fixed'), input('number', component.fixed_price_tax_excl, function (value) {
          component.fixed_price_tax_excl = Number(value || 0);
        })));
        grid.appendChild(fieldBlock(label('discount'), input('number', component.discount_percent, function (value) {
          component.discount_percent = Number(value || 0);
        })));
        grid.appendChild(fieldBlock(label('surcharge'), input('number', component.surcharge_tax_excl, function (value) {
          component.surcharge_tax_excl = Number(value || 0);
        })));
        card.appendChild(grid);
        card.appendChild(renderProducts(component));

        return card;
      }

      function renderProducts(component) {
        var wrapper = el('div', {'class': 'dydaps-pack-builder__products'});
        var selected = el('div', {'class': 'dydaps-pack-builder__selected-products'});
        if (!component.products.length) {
          selected.appendChild(el('p', {'class': 'text-muted'}, label('no-products')));
        }
        component.products.forEach(function (product, index) {
          selected.appendChild(renderSelectedProduct(component, product, index));
        });
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
                results.appendChild(renderSearchResult(component, product));
              });
            });
        });

        return wrapper;
      }

      function renderSelectedProduct(component, product, index) {
        var row = el('div', {'class': 'dydaps-pack-builder__product'});
        if (product.image) {
          row.appendChild(el('img', {src: product.image, alt: ''}));
        }
        var text = el('div');
        text.appendChild(el('strong', {}, product.name || ''));
        if (product.attributes_text) {
          text.appendChild(el('span', {}, product.attributes_text));
        }
        if (product.reference) {
          text.appendChild(el('small', {}, product.reference));
        }
        row.appendChild(text);

        var defaultButton = el('button', {type: 'button', class: product.is_default ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary'}, label('default'));
        defaultButton.disabled = !canUpdate;
        defaultButton.addEventListener('click', function () {
          component.products.forEach(function (candidate) {
            candidate.is_default = 0;
          });
          product.is_default = 1;
          render();
        });
        row.appendChild(defaultButton);

        var removeButton = el('button', {type: 'button', class: 'btn btn-sm btn-outline-danger'}, label('remove'));
        removeButton.disabled = !canUpdate;
        removeButton.addEventListener('click', function () {
          component.products.splice(index, 1);
          if (component.products.length && !component.products.some(function (candidate) { return candidate.is_default; })) {
            component.products[0].is_default = 1;
          }
          render();
        });
        row.appendChild(removeButton);

        return row;
      }

      function renderSearchResult(component, product) {
        var row = el('div', {'class': 'dydaps-pack-builder__product dydaps-pack-builder__product--result'});
        if (product.image) {
          row.appendChild(el('img', {src: product.image, alt: ''}));
        }
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
          if (!component.products.some(function (candidate) {
            return candidate.id_product === product.id_product && candidate.id_product_attribute === product.id_product_attribute;
          })) {
            component.products.push(normalizeComponent({products: [product]}, 0).products[0]);
            if (!component.products.some(function (candidate) { return candidate.is_default; })) {
              component.products[0].is_default = 1;
            }
          }
          render();
        });
        row.appendChild(addButton);

        return row;
      }

      root.querySelector('[data-pack-builder-add-component]').addEventListener('click', function () {
        components.push(normalizeComponent({name: label('component') + ' ' + (components.length + 1)}, components.length));
        render();
      });

      render();
    });
  });
}());
