<?php
add_action('acf/include_fields', function () {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_textoiconos_001',
        'title' => 'textoiconos',
        'fields' => array(
            array(
                'key' => 'field_textoiconos_titulo',
                'label' => 'titulo_textoiconos',
                'name' => 'titulo_textoiconos',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'maxlength' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ),
            array(
                'key' => 'field_textoiconos_parrafo',
                'label' => 'parrafo_textoiconos',
                'name' => 'parrafo_textoiconos',
                'aria-label' => '',
                'type' => 'wysiwyg',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '',
                'maxlength' => '',
                'rows' => 4,
                'new_lines' => 'wpautop',
            ),
            array(
                'key' => 'field_textoiconos_repetidor',
                'label' => 'repetidor_iconos',
                'name' => 'repetidor_iconos',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'layout' => 'block',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => '',
                'button_label' => 'Agregar Icono',
                'rows_per_page' => 20,
                'sub_fields' => array(
                    array(
                        'key' => 'field_textoiconos_icono',
                        'label' => 'icono',
                        'name' => 'icono',
                        'aria-label' => '',
                        'type' => 'image',
                        'instructions' => 'Subir icono o imagen',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'return_format' => 'array',
                        'library' => 'all',
                        'preview_size' => 'thumbnail',
                        'parent_repeater' => 'field_textoiconos_repetidor',
                    ),
                    array(
                        'key' => 'field_textoiconos_titulo_icono',
                        'label' => 'titulo_icono',
                        'name' => 'titulo_icono',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_textoiconos_repetidor',
                    ),
                    array(
                        'key' => 'field_textoiconos_parrafo_icono',
                        'label' => 'parrafo_icono',
                        'name' => 'parrafo_icono',
                        'aria-label' => '',
                        'type' => 'wysiwyg',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'placeholder' => '',
                        'maxlength' => '',
                        'rows' => 4,
                        'new_lines' => 'wpautop',
                        'parent_repeater' => 'field_textoiconos_repetidor',
                    )
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/textoiconos',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ));
});

function textoiconos_acf()
{
    acf_register_block_type([
        'name'        => 'textoiconos',
        'title'        => __('textoiconos', 'tictac'),
        'description'    => __('Bloque con título, párrafo y repetidor de iconos con texto', 'tictac'),
        'render_callback'  => 'textoiconos',
        'mode'        => 'preview',
        'icon'        => 'grid-view',
        'keywords'      => ['custom', 'textoiconos', 'iconos', 'servicios'],
    ]);
}

add_action('acf/init', 'textoiconos_acf');

function textoiconos_scripts()
{
    if (!is_admin()) {
        wp_enqueue_style('textoiconos', get_stylesheet_directory_uri() . '/assets/functions/blocks/textoiconos/textoiconos.min.css');
    }
}
add_action('wp_enqueue_scripts', 'textoiconos_scripts');

function textoiconos($block)
{
    $titulo = get_field("titulo_textoiconos");
    $parrafo = get_field("parrafo_textoiconos");
    $iconos = get_field("repetidor_iconos");
?>
    <div class="container textoiconos">
        <div class="textoiconos-content">
            <?php if ($titulo): ?>
                <h2 class="textoiconos-titulo"><?= $titulo ?></h2>
            <?php endif; ?>
            
            <?php if ($parrafo): ?>
                <div class="textoiconos-parrafo"><?= wpautop($parrafo) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($iconos) : ?>
            <div class="textoiconos-grid">
                <?php foreach ($iconos as $icono) : ?>
                    <div class="icono-item">
                        <?php if ($icono['icono']): ?>
                            <div class="icono-imagen">
                                <img src="<?php echo esc_url($icono['icono']['url']); ?>" 
                                     alt="<?php echo esc_attr($icono['icono']['alt']); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="icono-contenido">
                            <?php if ($icono['titulo_icono']): ?>
                                <h3 class="icono-titulo"><?= $icono['titulo_icono'] ?></h3>
                            <?php endif; ?>

                            <?php if ($icono['parrafo_icono']): ?>
                                <div class="icono-parrafo"><?= wpautop($icono['parrafo_icono']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .textoiconos {
            box-shadow: 0px 0px 15px 0px #2E2D2C33;
            backdrop-filter: blur(3px);
            border-radius: 20px;
            padding: 50px !important;
            margin-top: 50px;
        }

        .textoiconos-content {
            text-align: center;
            margin-bottom: 50px;
        }

        .textoiconos-titulo {
            color: #871727;
            font-size: 2.5rem;
            margin-bottom: 30px;
            line-height: 1.2;
            font-family: 'manhaj' !important;
        }

        .textoiconos-parrafo {
            margin: 0 auto;
            font-size: 1.1rem;
            line-height: 1.6;
            color: #2E2D2C;
            max-width: 800px;
        }

        .textoiconos-parrafo p {
            margin-bottom: 20px;
        }

        .textoiconos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            align-items: start;
        }

        .icono-item {
            text-align: center;
            padding: 30px;
            transition: all 0.3s ease;
        }



        .icono-imagen {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 80px;
        }

        .icono-imagen img {
            max-width: 100px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .icono-contenido {
            text-align: center;
        }

        .icono-titulo {
            color: #DB7461;
    font-size: 25px;
    margin-bottom: 15px;
    line-height: 1.3;
    font-family: 'Duran-Medium';
    text-transform: uppercase;
    letter-spacing: 4px;
        }

        .icono-parrafo {
            color: #2E2D2C;
            font-size: 1rem !important;
            line-height: 28px !important;
            margin: 0;
            letter-spacing: 0.02em !important;


        }

        .icono-parrafo p {
            margin-bottom: 15px;
        }

        .icono-parrafo p:last-child {
            margin-bottom: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .textoiconos {
                padding: 40px 20px !important;
            }

            .textoiconos-titulo {
                font-size: 2rem;
                margin-bottom: 20px;
            }

            .textoiconos-parrafo {
                font-size: 1rem;
            }

            .textoiconos-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .icono-item {
                padding: 25px 20px;
            }

            .icono-titulo {
                font-size: 1.3rem;
                margin-bottom: 12px;
            }

            .icono-parrafo {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .textoiconos-titulo {
                font-size: 1.8rem;
            }

            .icono-item {
                padding: 20px 15px;
            }





            .icono-titulo {
                font-size: 1.2rem;
            }
        }
    </style>
<?php
}