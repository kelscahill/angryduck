import domReady from '@roots/sage/client/dom-ready';
/* eslint-disable */
let Rellax = require('rellax');
var rellax = document.querySelector('.js-rellax');
if (rellax) {
  new Rellax('.js-rellax');
}

import inView from 'in-view';
import $ from 'jquery';

/**
 * Application entrypoint
 */
domReady(async () => {
  // Add class if is mobile
  function isMobile() {
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
      return true;
    }
    return false;
  }
  // Add class if is mobile
  if (isMobile()) {
    $('html').addClass(' touch');
  } else if (!isMobile()){
    $('html').addClass(' no-touch');
  }

  /**
  * Add inview class on scroll if has-animation class.
  */
  if (!isMobile()) {
    inView('.js-inview').on('enter', function() {
      $("*[data-animation]").each(function() {
        var animation = $(this).attr('data-animation');
        if (inView.is(this)) {
          $(this).addClass("is-inview");
          $(this).addClass(animation);
        }
      });
    });
  }

  /**
  * Remove Active Classes when clicking outside menus and modals
  */
  $(document).click(function(event) {
    if (!$(event.target).closest(".c-nav-drawer").length) {
      $("html").find(".menu-is-active").removeClass("menu-is-active");
    }

    // Remove active class from user menu when clicking outside
    if (!$(event.target).closest(".c-user-menu").length) {
      $(".c-user-menu").removeClass("this-is-active");
    }
  });

  // Expires after one day
  var setCookie = function(name, value) {
    var date = new Date(),
        expires = 'expires=';
    date.setDate(date.getDate() + 1);
    expires += date.toGMTString();
    document.cookie = name + '=' + value + '; ' + expires + '; path=/; SameSite=Strict;';
  }

  var getCookie = function(name) {
    var allCookies = document.cookie.split(';'),
      cookieCounter = 0,
      currentCookie = '';
    for (cookieCounter = 0; cookieCounter < allCookies.length; cookieCounter++) {
      currentCookie = allCookies[cookieCounter];
      while (currentCookie.charAt(0) === ' ') {
        currentCookie = currentCookie.substring(1, currentCookie.length);
      }
      if (currentCookie.indexOf(name + '=') === 0) {
        return currentCookie.substring(name.length + 1, currentCookie.length);
      }
    }
    return false;
  }

  $('.js-alert-close').click(function(e) {
    e.preventDefault();
    $('.js-alert').addClass('is-hidden');
    setCookie('alert', 'true');
  });

  var showAlert = function() {
    $('.js-alert').fadeIn();
    $('.js-alert').removeClass('is-hidden');
  }

  var hideAlert = function() {
    $('.js-alert').fadeOut();
    $('.js-alert').addClass('is-hidden');
  }

  if (getCookie('alert')) {
    hideAlert();
  } else {
    showAlert();
  }

  // Smooth scrolling on anchor clicks
  $(function() {
    $('a[href*="#"]:not([href="#"])').click(function() {
      $('.nav__primary, .nav-toggler').removeClass('main-nav-is-active');
      if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
        var target = $(this.hash);
        target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
        if (target.length) {
          $('html, body').animate({
            scrollTop: target.offset().top - 50
          }, 1000);
          return false;
        }
      }
    });
  });

  /**
   * Slick sliders
   */
  $('.js-slick-testimonials').slick({
    arrows: false,
    dots: true,
    infinite: false,
    speed: 300,
    slidesToShow: 3,
    slidesToScroll: 3,
    responsive: [
      {
        breakpoint: 1200,
        settings: {
          slidesToShow: 2,
          slidesToScroll: 2,
        }
      },
      {
        breakpoint: 850,
        settings: {
          slidesToShow: 1,
          slidesToScroll: 1,
        }
      }
    ]
  });

  var $slickGalleryImages = $('.js-product-gallery');
  var $slickGalleryNav = $('.js-product-gallery-nav');
  if ($slickGalleryImages.length) {
    $slickGalleryImages.slick({
      speed: 500,
      slidesToShow: 1,
      slidesToScroll: 1,
      arrows: false,
      fade: true,
      dots: true,
      asNavFor: $slickGalleryNav
    });

    $slickGalleryNav.slick({
      slidesToShow: 4,
      slidesToScroll: 1,
      asNavFor: $slickGalleryImages,
      vertical: true,
      verticalSwiping: true,
      draggable: true,
      focusOnSelect: true,
    });
  }

  /**
   * toggleClasses()
   *
   * @description
   * toggle specific classes based on data-attr of clicked element
   *
   * @requires
   * 'js-toggle' class and a data-attr with the element to be
   * toggled's class name both applied to the clicked element
   *
   * @example
   * <span class="js-toggle" data-toggled="toggled-class">Toggler</span>
   * <div class="toggled-class">This element's class will be toggled</div>
   *
   * @param {Element} element - element to toggle.
   */
  function toggleClasses(element) {
    const togglePrefix = element.dataset.prefix || 'this';
    let toggled = null;

    // If the element you need toggled is relative to the toggle, add the
    // .js-this class to the parent element and "this" to the data-toggled attr.
    if (element.dataset.toggled == "this") {
      toggled = element.closest('.js-this');
    }
    else {
      toggled = document.querySelector(element.dataset.toggled);
    }

    if (toggled) {
      toggled.classList.toggle(togglePrefix + '-is-active');
    }
  }

  function setUtilities(parentEl) {
    // Toggle class
    [...parentEl.querySelectorAll('.js-toggle:not(.js-toggle--initialized)')].forEach((el) => {
      el.classList.add('js-toggle--initialized');
      el.addEventListener('click', (e) => {
        if (!el.classList.contains('js-not-stop')) {
          e.preventDefault();
          e.stopPropagation();
        }
        toggleClasses(el);
      });
    });

    // Toggle parent class
    [...parentEl.querySelectorAll('.js-toggle-parent:not(.js-toggle-parent--initialized)')].forEach((el) => {
      el.classList.add('js-toggle-parent--initialized');
      el.addEventListener('click', (e) => {
        if (!el.classList.contains('js-not-stop')) {
          e.preventDefault();
        }
        el.classList.toggle('this-is-active');
        el.parentElement.classList.toggle('this-is-active');
      });
    });
  }

  setUtilities(document);
  console.log('App.js initialization complete');
});

/**
 * @see {@link https://webpack.js.org/api/hot-module-replacement/}
 */
if (import.meta.webpackHot) import.meta.webpackHot.accept(console.error);
