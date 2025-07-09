<!DOCTYPE html>

<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <title><?php wp_title(); ?></title>
  <?php wp_head(); ?>
  <!-- Definir viewporrrrrrrrrrt para dispositivos web móviles -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet preload" as="style" media="all" href="<?php bloginfo('stylesheet_url'); ?>" />
  <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
  <link rel="icon" type="image/x-icon" href="<?= site_url('/favicon.ico'); ?>">



</head>
<?php $post_slug = get_post_field('post_name', get_post()); ?>

<body <?php body_class(); ?> id="<?php echo $post_slug; ?>">



  <?php
  $phone = get_field("footer_telefono_1", "options");
  $whatsapp = get_field("footer_telefono_2", "options");
  $fax = get_field("fax", "options");
  $email = get_field("footer_email", "options");
  $info = get_field("field_footer_informacion", "options");
  $instagram = get_field("instagram", "options");
  $facebook = get_field("facebook", "options");
  $linkedin = get_field("ln", "options");
  $direccion = get_field("direccion", "options");
  $catalogo = get_field("catalogo", "options");
  $upload_dir = wp_upload_dir();
  ?>


  <header id="header" class="navBar">

    <div class="divheader flex items-center justify-between">



      <!-- Desktop Navigation - Cambiado de md:flex a xl:flex -->
      <nav class="hidden xl:flex flex-1 justify-center">
        <?php
        wp_nav_menu(array(
          'theme_location' => 'menu-header',
          'menu_class'    => 'flex items-center space-x-8',
          'container'     => false,
          'items_wrap'    => '<ul class="%2$s">%3$s</ul>',
          'fallback_cb'   => false
        ));
        ?>
        <a class="buttonreservar" href="#procesocompra">RESERVAR<img style="width:20px" src="<?php echo $upload_dir['baseurl']; ?>/2025/07/87070a4063df51d50fd4bae645befbb94df703e2.gif"></a>
      </nav>

      <!-- Mobile Menu Button - Cambiado de md:hidden a xl:hidden -->
      <div class="desplegablemenuopen">
        <div class="menuOpen xl:hidden flex flex-col justify-center items-center cursor-pointer">
          <span></span>
          <span></span>
          <span></span>
        </div>
        <?php echo do_shortcode('[gtranslate]'); ?>
      </div>

    </div>

    <!-- Mobile Navigation -->
    <nav id="menu" class="menu-mobile w-full bg-white">
      <?php
      wp_nav_menu(array(
        'theme_location' => 'menu-header',
        'menu_class'    => 'flex flex-col items-center space-y-4',
        'container'     => false,
        'items_wrap'    => '<ul class="%2$s">%3$s</ul>',
        'fallback_cb'   => false
      ));
      ?>
    </nav>


    <!-- Panel de búsqueda a pantalla completa (para móviles) -->
    <div id="search-panel" class="search-panel w-full bg-white">
      <div class="search-form-container p-4">
        <form role="search" method="get" class="woocommerce-product-search" action="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">
          <div class="search-input-wrapper">
            <button type="submit" class="search-submit">
              <img src="<?php echo $upload_dir['baseurl']; ?>/2025/03/Vector-9.svg" alt="">
            </button>
            <input type="search" class="search-field" placeholder="Buscar" value="<?php echo get_search_query(); ?>" name="s" />
            <input type="hidden" name="post_type" value="product" />
            <input type="hidden" name="prevent_redirect" value="1" />
          </div>
        </form>
      </div>
    </div>
  </header>

  <style>
    /* Estilos para el buscador según la imagen */
    .search-container {
      margin-left: 20px;
      position: relative;
    }

    /* Estilos para pantallas grandes */
    .desktop-search .search-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      border: 1px solid #333;
      border-radius: 8px;
      overflow: hidden;
      background-color: white;
      width: 240px;
    }

    .desktop-search .search-field {
      flex: 1;
      border: none;
      padding: 8px 8px 8px 0;
      font-family: 'MontserratAlternates-Medium', sans-serif;
      font-size: 16px;
      outline: none;
      background: transparent;
      color: #777;
    }

    .desktop-search .search-field::placeholder {
      color: #999;
    }

    .search-submit {
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 8px 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .search-submit img {
      width: 20px;
      height: 20px;
    }

    /* Estilos para móviles */
    .mobile-search-icon {
      display: none;
      /* Oculto por defecto, se muestra solo en pantallas pequeñas */
    }

    .search-icon-toggle {
      cursor: pointer;
      padding: 8px;
    }

    .search-icon-toggle img {
      width: 20px;
      height: 20px;
    }

    /* Panel de búsqueda a pantalla completa */
    .search-panel {
      top: 0;
      left: 0;
      width: 100%;
      background-color: white;
      z-index: 999999;
      display: none;
      flex-direction: column;
      overflow-y: auto;
    }

    .search-panel.active {
      display: flex;
    }

    .search-form-container {
      padding: 20px !important;
    }

    .search-panel .search-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      border: 1px solid #333;
      border-radius: 8px;
      overflow: hidden;
      background-color: white;
      width: 100%;
    }

    .search-panel .search-field {
      flex: 1;
      border: none;
      padding: 12px 12px 12px 0;
      font-family: 'MontserratAlternates-Medium', sans-serif;
      font-size: 16px;
      outline: none;
      background: transparent;
      color: #777;
    }

    #woocommerce-product-search-field {
      background-color: #ffffff !important;
    }

    /* Media queries para comportamiento responsivo - Cambiado a 1100px */
    @media (max-width: 1100px) {
      .desktop-search {
        display: none;
        /* Ocultar buscador normal en pantallas pequeñas */
      }

      .mobile-search-icon {
        display: block;
        /* Mostrar icono de lupa en pantallas pequeñas */
      }
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchToggle = document.querySelector('.search-icon-toggle');
      const searchPanel = document.getElementById('search-panel');

      if (searchToggle && searchPanel) {
        searchToggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          // Alternar clase active
          searchPanel.classList.toggle('active');

          // Si el panel está activo, enfocar el campo de búsqueda
          if (searchPanel.classList.contains('active')) {
            const searchField = searchPanel.querySelector('.search-field');
            if (searchField) {
              setTimeout(function() {
                searchField.focus();
              }, 100);
            }
          }
        });

        // También cerrar el panel al hacer clic en el mismo icono o fuera del panel
        document.addEventListener('click', function(e) {
          if (searchPanel.classList.contains('active') &&
            !searchPanel.contains(e.target) &&
            !searchToggle.contains(e.target)) {
            searchPanel.classList.remove('active');
          }
        });

        // Prevenir cierre al hacer clic dentro del panel
        searchPanel.addEventListener('click', function(e) {
          e.stopPropagation();
        });
      }
    });
  </script>