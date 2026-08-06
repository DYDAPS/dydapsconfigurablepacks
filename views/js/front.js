(function () {
  'use strict';

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
            option.textContent = '#' + product.id_product + (parseInt(product.id_product_attribute, 10) > 0 ? ' / ' + product.id_product_attribute : '');
            select.appendChild(option);
          });
          wrapper.appendChild(select);
          body.appendChild(wrapper);
        });
      });

      var add = root.querySelector('[data-pack-add]');
      add.addEventListener('click', function () {
        var components = [];
        root.querySelectorAll('[data-pack-selection]').forEach(function (select) {
          var parts = String(select.value).split(':');
          components.push({
            id_component: parseInt(select.getAttribute('data-pack-selection'), 10),
            id_product: parseInt(parts[0], 10),
            id_product_attribute: parseInt(parts[1] || '0', 10),
            quantity: 1
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
