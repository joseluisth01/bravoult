<?php
add_action('acf/include_fields', function () {
    if (! function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key' => 'group_textosemaforoslider_001',
        'title' => 'textosemaforoslider',
        'fields' => array(
            array(
                'key' => 'field_textosemaforoslider_titulo',
                'label' => 'titulo_textosemaforoslider',
                'name' => 'titulo_textosemaforoslider',
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
                'key' => 'field_textosemaforoslider_parrafo_inicial',
                'label' => 'parrafo_inicial',
                'name' => 'parrafo_inicial',
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
                'key' => 'field_textosemaforoslider_slider',
                'label' => 'slider_semaforo',
                'name' => 'slider_semaforo',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => 'Cada elemento será una slide del slider',
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
                'button_label' => 'Agregar Slide',
                'rows_per_page' => 20,
                'sub_fields' => array(
                    array(
                        'key' => 'field_textosemaforoslider_titulo_slide',
                        'label' => 'titulo_slide',
                        'name' => 'titulo_slide',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => 'Título H3 de cada slide',
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
                        'parent_repeater' => 'field_textosemaforoslider_slider',
                    ),
                    array(
                        'key' => 'field_textosemaforoslider_pasos',
                        'label' => 'pasos_semaforo',
                        'name' => 'pasos_semaforo',
                        'aria-label' => '',
                        'type' => 'repeater',
                        'instructions' => 'Pasos del semáforo para esta slide',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'layout' => 'table',
                        'pagination' => 0,
                        'min' => 0,
                        'max' => 0,
                        'collapsed' => '',
                        'button_label' => 'Agregar Paso',
                        'rows_per_page' => 20,
                        'sub_fields' => array(
                            array(
                                'key' => 'field_textosemaforoslider_titulo_paso',
                                'label' => 'titulo_paso',
                                'name' => 'titulo_paso',
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
                                'parent_repeater' => 'field_textosemaforoslider_pasos',
                            ),
                            array(
                                'key' => 'field_textosemaforoslider_parrafo_paso',
                                'label' => 'parrafo_paso',
                                'name' => 'parrafo_paso',
                                'aria-label' => '',
                                'type' => 'textarea',
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
                                'rows' => 3,
                                'placeholder' => '',
                                'parent_repeater' => 'field_textosemaforoslider_pasos',
                            )
                        ),
                        'parent_repeater' => 'field_textosemaforoslider_slider',
                    )
                ),
            ),
            array(
                'key' => 'field_textosemaforoslider_parrafo_final',
                'label' => 'parrafo_final',
                'name' => 'parrafo_final',
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
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/textosemaforoslider',
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

function textosemaforoslider_acf()
{
    acf_register_block_type([
        'name'        => 'textosemaforoslider',
        'title'        => __('textosemaforoslider', 'tictac'),
        'description'    => __('Bloque con título, párrafo, slider de semáforos y párrafo final', 'tictac'),
        'render_callback'  => 'textosemaforoslider',
        'mode'        => 'preview',
        'icon'        => 'slides',
        'keywords'      => ['custom', 'textosemaforoslider', 'semaforo', 'slider', 'pasos'],
    ]);
}

add_action('acf/init', 'textosemaforoslider_acf');

function textosemaforoslider_scripts()
{
    if (!is_admin()) {
        wp_enqueue_style('textosemaforoslider', get_stylesheet_directory_uri() . '/assets/functions/blocks/textosemaforoslider/textosemaforoslider.min.css');
    }
}
add_action('wp_enqueue_scripts', 'textosemaforoslider_scripts');

function textosemaforoslider($block)
{
    $titulo = get_field("titulo_textosemaforoslider");
    $parrafo_inicial = get_field("parrafo_inicial");
    $slides = get_field("slider_semaforo");
    $parrafo_final = get_field("parrafo_final");
    $upload_dir = wp_upload_dir();

    if (empty($slides)) return;
?>
    <div class="container textosemaforoslider">
        <div class="textosemaforoslider-content">
            <?php if ($titulo): ?>
                <h2 class="textosemaforoslider-titulo"><?= $titulo ?></h2>
            <?php endif; ?>
            
            <?php if ($parrafo_inicial): ?>
                <div class="textosemaforoslider-parrafo-inicial"><?= wpautop($parrafo_inicial) ?></div>
            <?php endif; ?>
        </div>

        <div class="textosemaforoslider-slider-container">
            <div class="textosemaforoslider-slider">
                <div class="textosemaforoslider-track">
                    <?php foreach ($slides as $slide_index => $slide) : ?>
                        <div class="textosemaforoslider-slide" data-slide="<?= $slide_index ?>">
                            <?php if ($slide['titulo_slide']): ?>
                                <h3 class="slide-titulo"><?= $slide['titulo_slide'] ?></h3>
                            <?php endif; ?>

                            <?php if ($slide['pasos_semaforo']) : ?>
                                <div class="slide-semaforo">
                                    <div class="semaforo-contenedor">
                                        <?php foreach ($slide['pasos_semaforo'] as $paso_index => $paso) : ?>
                                            <div class="semaforo-item" data-paso="<?= $paso_index ?>">
                                                <div class="semaforo-textos">
                                                    <?php if ($paso['titulo_paso']) : ?>
                                                        <div class="semaforo-titulo-paso"><?= $paso['titulo_paso'] ?></div>
                                                    <?php endif; ?>
                                                    <div class="divrayas">

                                                        <?php if ($paso['parrafo_paso']) : ?>
                                                            <p class="semaforo-parrafo-paso"><?= $paso['parrafo_paso'] ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Navegación del slider -->
            <div class="textosemaforoslider-navigation">
                <button class="nav-btn prev-btn" onclick="changeSlide(-1)">
                    <img src="<?php echo $upload_dir['baseurl']; ?>/2025/07/Vector-12.svg" alt="Anterior">
                </button>
                <button class="nav-btn next-btn" onclick="changeSlide(1)">
                    <img src="<?php echo $upload_dir['baseurl']; ?>/2025/07/Vector-13.svg" alt="Siguiente">
                </button>
            </div>
        </div>

        <?php if ($parrafo_final): ?>
            <div class="textosemaforoslider-content">
                <div class="textosemaforoslider-parrafo-final"><?= wpautop($parrafo_final) ?></div>
            </div>
        <?php endif; ?>
    </div>

    

    <script>
        let currentSlideIndex = 0;
        const totalSlides = <?= count($slides) ?>;
        let semaforoIntervals = [];

        function updateSlider() {
            const track = document.querySelector('.textosemaforoslider-track');
            
            track.style.transform = `translateX(-${currentSlideIndex * 100}%)`;

            // Reiniciar animación del semáforo en la slide actual
            startSemaforoAnimation(currentSlideIndex);
        }

        function changeSlide(direction) {
            // Limpiar intervalos anteriores
            clearAllSemaforoIntervals();
            
            currentSlideIndex += direction;
            
            if (currentSlideIndex >= totalSlides) {
                currentSlideIndex = 0;
            } else if (currentSlideIndex < 0) {
                currentSlideIndex = totalSlides - 1;
            }
            
            updateSlider();
        }

        function goToSlide(slideIndex) {
            clearAllSemaforoIntervals();
            currentSlideIndex = slideIndex;
            updateSlider();
        }

        function clearAllSemaforoIntervals() {
            semaforoIntervals.forEach(interval => clearInterval(interval));
            semaforoIntervals = [];
            
            // Resetear todos los semáforos
            document.querySelectorAll('.semaforo-item').forEach(item => {
                item.classList.remove('active');
            });
        }

        function startSemaforoAnimation(slideIndex) {
            const currentSlide = document.querySelector(`[data-slide="${slideIndex}"]`);
            if (!currentSlide) return;

            const items = currentSlide.querySelectorAll('.semaforo-item');
            let index = 0;

            function resetItems() {
                items.forEach((item, i) => {
                    item.classList.remove('active');
                });
            }

            function activateNext() {
                if (index < items.length) {
                    items[index].classList.add('active');
                    index++;
                    const timeoutId = setTimeout(activateNext, 1500); // Cambié de 2000 a 1500ms
                    semaforoIntervals.push(timeoutId);
                } else {
                    const timeoutId = setTimeout(() => {
                        resetItems();
                        const timeoutId2 = setTimeout(() => {
                            index = 0;
                            activateNext();
                        }, 1500); // Cambié de 2000 a 1500ms
                        semaforoIntervals.push(timeoutId2);
                    }, 1500); // Cambié de 2000 a 1500ms
                    semaforoIntervals.push(timeoutId);
                }
            }

            if (items.length > 0) {
                activateNext();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Iniciar animación en la primera slide
            startSemaforoAnimation(0);
            
            // Eliminé el auto-play del slider
        });
    </script>
<?php
}