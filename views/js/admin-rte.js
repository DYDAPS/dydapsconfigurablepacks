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

  var selector = 'textarea.autoload_rte';

  function findTextareas() {
    return document.querySelectorAll(selector);
  }

  function resolveBaseAdminDir() {
    if (window.baseAdminDir) {
      return String(window.baseAdminDir);
    }
    var path = window.location.pathname.split('/');
    for (var i = path.length - 1; i >= 0; i -= 1) {
      if (path[i] !== '') {
        return '/' + path[i] + '/';
      }
    }

    return '/';
  }

  function resolveShopRoot() {
    // PrestaShop admin pages define the shop root in `baseDir` (typically "/").
    if (window.baseDir) {
      var baseDir = String(window.baseDir);
      if (/^\//.test(baseDir)) {
        return baseDir;
      }
    }
    // Fallback: strip the first path segment (the admin directory) from the
    // admin base URL so "/admin204v5tyvjwkbfmgjncr/" becomes "/".
    var parts = resolveBaseAdminDir().split('/');
    parts.splice(1, 1);

    return parts.join('/');
  }

  function isVisible(element) {
    if (!element || element.getClientRects().length === 0) {
      return false;
    }
    var style = window.getComputedStyle(element);

    return style.display !== 'none' && style.visibility !== 'hidden';
  }

  function findUninitializedTextareas() {
    var uninitialized = [];
    findTextareas().forEach(function (textarea) {
      // Editors are only created for visible textareas: initializing TinyMCE
      // on a hidden tab panel renders an editor with zero dimensions. The tab
      // switcher calls DydapsPacksRte.init() when a panel becomes visible.
      if (isVisible(textarea) && (!window.tinyMCE || !window.tinyMCE.get(textarea.id))) {
        uninitialized.push(textarea);
      }
    });

    return uninitialized;
  }

  function updateCounter(editor) {
    var textarea = document.getElementById(editor.id);
    if (!textarea) {
      return;
    }
    var max = editor.getBody() ? editor.getBody().textContent.length : 0;
    var counter = textarea.getAttribute('counter');
    var counterType = textarea.getAttribute('counter_type');
    var parent = textarea.parentNode;
    if (!parent) {
      return;
    }
    var current = parent.querySelector('span.currentLength');
    var maxLength = parent.querySelector('span.maxLength');
    if (current) {
      current.textContent = max;
    }
    if (maxLength && counter !== null && counterType !== 'recommended' && max > parseInt(counter, 10)) {
      maxLength.classList.add('text-danger');
    } else if (maxLength) {
      maxLength.classList.remove('text-danger');
    }
  }

  function initTinyMce() {
    if (!window.tinyMCE) {
      return;
    }
    var targets = findUninitializedTextareas();
    if (!targets.length) {
      return;
    }
    var root = resolveShopRoot();
    window.tinyMCEPreInit = window.tinyMCEPreInit || {};
    window.tinyMCEPreInit.base = root + 'js/tiny_mce';
    window.tinyMCEPreInit.suffix = '.min';

    var selector = targets.map(function (textarea) {
      return '#' + textarea.id;
    }).join(',');

    window.tinyMCE.init({
      selector: selector,
      plugins: 'align colorpicker link image table media placeholder lists advlist code autoresize hr',
      browser_spellcheck: true,
      toolbar1: 'code,colorpicker,bold,italic,underline,strikethrough,blockquote,link,align,bullist,numlist,table,image,media,formatselect,hr',
      toolbar2: '',
      language: window.iso_user || 'en',
      skin: 'prestashop',
      mobile: {
        theme: 'mobile',
        plugins: ['lists', 'align', 'link', 'table', 'placeholder', 'advlist', 'code', 'hr'],
        toolbar: 'undo code colorpicker bold italic underline strikethrough blockquote link align bullist numlist table formatselect styleselect hr'
      },
      menubar: false,
      statusbar: false,
      relative_urls: false,
      convert_urls: false,
      entity_encoding: 'raw',
      extended_valid_elements: 'em[class|name|id],@[role|data-*|aria-*]',
      valid_children: '+*[*]',
      valid_elements: '*[*]',
      rel_list: [{title: 'nofollow', value: 'nofollow'}],
      setup: function (editor) {
        editor.on('init', function () {
          updateCounter(editor);
        });
        editor.on('input', function () {
          updateCounter(editor);
          editor.save();
        });
        editor.on('change', function () {
          updateCounter(editor);
          editor.save();
        });
        editor.on('blur', function () {
          editor.save();
        });
      }
    });
  }

  function loadTinyMce() {
    if (window.tinyMCE) {
      initTinyMce();

      return;
    }
    if (!findTextareas().length) {
      return;
    }
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = resolveShopRoot() + 'js/tiny_mce/tinymce.min.js';
    script.onload = initTinyMce;
    script.onerror = function () {
      if (window.console) {
        console.error('Configurable packs: unable to load TinyMCE from ' + script.src);
      }
    };
    document.head.appendChild(script);
  }

  function bindFormSave() {
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        findTextareas().forEach(function (textarea) {
          if (window.tinyMCE && window.tinyMCE.get(textarea.id)) {
            window.tinyMCE.get(textarea.id).save();
          }
        });
      });
    });
  }

  window.DydapsPacksRte = {
    init: function () {
      loadTinyMce();
    }
  };

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    loadTinyMce();
  } else {
    document.addEventListener('DOMContentLoaded', loadTinyMce);
  }
  document.addEventListener('DOMContentLoaded', bindFormSave);
}());
