<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function shk_elementor_slides_normalize_media_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
        return '';
    }

    if ( 0 === strpos( $url, '//' ) ) {
        $url = 'https:' . $url;
    } elseif ( '/' === substr( $url, 0, 1 ) ) {
        $url = home_url( $url );
    } elseif ( ! preg_match( '~^https?://~i', $url ) ) {
        $url = home_url( '/' . ltrim( $url, '/' ) );
    }

    return wp_http_validate_url( $url ) ? $url : '';
}

/**
 * Register only after Elementor init, otherwise hooks may not attach
 * when this file is loaded too early by the theme.
 */
add_action( 'elementor/init', 'shk_elementor_slides_video_bootstrap' );
function shk_elementor_slides_video_bootstrap() {
    /**
     * Elementor compatibility:
     * Use generic hook (widget + section check), because direct
     * elementor/element/slides/section_slides/before_section_end
     * is not fired in some Elementor builds.
     */
    add_action( 'elementor/element/slides/section_slides/before_section_end', 'shk_elementor_slides_add_video_controls_direct', 10, 2 );
    add_action( 'elementor/element/before_section_end', 'shk_elementor_slides_add_video_controls_compat', 10, 3 );
    add_action( 'elementor/element/slides/section_slides/after_section_end', 'shk_elementor_slides_add_video_map_section', 10, 2 );
    add_filter( 'elementor/widget/render_content', 'shk_elementor_slides_render_video', 20, 2 );
}

function shk_elementor_slides_add_video_controls_direct( $element, $args ) {
    shk_elementor_slides_add_video_controls( $element );
}

function shk_elementor_slides_add_video_controls_compat( $element, $section_id, $args ) {
    if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
        return;
    }

    if ( 'slides' !== $element->get_name() || 'section_slides' !== (string) $section_id ) {
        return;
    }

    shk_elementor_slides_add_video_controls( $element );
}

function shk_elementor_slides_add_video_controls( $element ) {
    if ( ! class_exists( '\Elementor\Controls_Manager' ) || ! method_exists( $element, 'get_control' ) || ! method_exists( $element, 'update_control' ) ) {
        return;
    }

    $slides_control = $element->get_control( 'slides' );
    if ( empty( $slides_control ) || empty( $slides_control['fields'] ) || ! is_array( $slides_control['fields'] ) ) {
        return;
    }

    foreach ( $slides_control['fields'] as $field ) {
        if ( isset( $field['name'] ) && 'shk_slide_video_url' === $field['name'] ) {
            return;
        }
    }

    $slides_control['fields'][] = [
        'name'        => 'shk_slide_video_url',
        'label'       => __( 'Slide Video URL', 'plra-theme-child' ),
        'type'        => \Elementor\Controls_Manager::TEXT,
        'label_block' => true,
        'dynamic'     => [ 'active' => true ],
        'description' => __( 'MP4/WebM URL. If set, video overlays slide background.', 'plra-theme-child' ),
    ];

    $slides_control['fields'][] = [
        'name'        => 'shk_slide_video_poster',
        'label'       => __( 'Slide Video Poster (optional)', 'plra-theme-child' ),
        'type'        => \Elementor\Controls_Manager::TEXT,
        'label_block' => true,
        'dynamic'     => [ 'active' => true ],
    ];

    $element->update_control( 'slides', $slides_control );
}

function shk_elementor_slides_add_video_map_section( $element, $args ) {
    if ( ! class_exists( '\Elementor\Controls_Manager' ) || ! method_exists( $element, 'start_controls_section' ) ) {
        return;
    }

    $element->start_controls_section(
        'shk_slides_video_map_section',
        [
            'label' => __( 'Slide Videos (Fallback)', 'plra-theme-child' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]
    );

    $element->add_control(
        'shk_slide_videos_map',
        [
            'label'       => __( 'Video Map', 'plra-theme-child' ),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'rows'        => 6,
            'label_block' => true,
            'description' => __( "One line per slide: slide_number|video_url|poster_url\nExample:\n1|https://site/video1.mp4\n2|https://site/video2.mp4|https://site/poster2.jpg", 'plra-theme-child' ),
        ]
    );

    $element->end_controls_section();
}

function shk_elementor_parse_video_map_text( $raw ) {
    $map = [];
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return $map;
    }

    $lines = preg_split( '/\r\n|\r|\n/', trim( $raw ) );
    if ( ! is_array( $lines ) ) {
        return $map;
    }

    foreach ( $lines as $line ) {
        $line = trim( (string) $line );
        if ( '' === $line ) {
            continue;
        }

        $parts = array_map( 'trim', explode( '|', $line ) );
        if ( count( $parts ) < 2 ) {
            continue;
        }

        $num = (int) $parts[0];
        if ( $num < 1 ) {
            continue;
        }
        $index = $num - 1;

        $url = shk_elementor_slides_normalize_media_url( $parts[1] ?? '' );
        if ( '' === $url ) {
            continue;
        }

        $poster = shk_elementor_slides_normalize_media_url( $parts[2] ?? '' );

        $map[ $index ] = [
            'url'    => $url,
            'poster' => $poster,
        ];
    }

    return $map;
}

function shk_elementor_class_contains( $class_attr, $token ) {
    $class_attr = is_string( $class_attr ) ? $class_attr : '';
    $token = trim( (string) $token );
    if ( '' === $class_attr || '' === $token ) {
        return false;
    }
    $classes = preg_split( '/\s+/', trim( $class_attr ) );
    return is_array( $classes ) && in_array( $token, $classes, true );
}

function shk_elementor_inject_video_by_slide_number( $content, $video_map ) {
    if ( ! is_string( $content ) || '' === $content || empty( $video_map ) || ! is_array( $video_map ) ) {
        return $content;
    }

    if ( ! class_exists( 'DOMDocument' ) ) {
        return $content;
    }

    $libxml_previous = libxml_use_internal_errors( true );
    $dom = new DOMDocument( '1.0', 'UTF-8' );
    $wrapped = '<div id="shk-slides-video-wrap">' . $content . '</div>';
    $loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    if ( ! $loaded ) {
        libxml_clear_errors();
        libxml_use_internal_errors( $libxml_previous );
        return $content;
    }

    $xpath = new DOMXPath( $dom );
    $nodes = $xpath->query( '//*[@class]' );
    if ( ! $nodes ) {
        libxml_clear_errors();
        libxml_use_internal_errors( $libxml_previous );
        return $content;
    }

    $sequential_slide_index = 0;
    foreach ( $nodes as $node ) {
        if ( ! ( $node instanceof DOMElement ) ) {
            continue;
        }

        if ( ! shk_elementor_class_contains( $node->getAttribute( 'class' ), 'swiper-slide' ) ) {
            continue;
        }

        // Skip cloned swiper slides if they exist in HTML.
        if ( shk_elementor_class_contains( $node->getAttribute( 'class' ), 'swiper-slide-duplicate' ) ) {
            continue;
        }

        $sequential_slide_index++;

        $aria_label = (string) $node->getAttribute( 'aria-label' );
        if ( '' !== $aria_label && preg_match( '/^\s*(\d+)\s*\/\s*\d+\s*$/u', $aria_label, $m ) ) {
            $slide_num = (int) $m[1];
        } else {
            // Fallback: use sequential non-duplicate slide order.
            $slide_num = $sequential_slide_index;
        }
        if ( $slide_num < 1 ) {
            continue;
        }
        $index = $slide_num - 1;
        if ( empty( $video_map[ $index ] ) || empty( $video_map[ $index ]['url'] ) ) {
            continue;
        }

        $inner = null;
        foreach ( $node->getElementsByTagName( 'div' ) as $div ) {
            if ( $div instanceof DOMElement && shk_elementor_class_contains( $div->getAttribute( 'class' ), 'swiper-slide-inner' ) ) {
                $inner = $div;
                break;
            }
        }
        if ( ! ( $inner instanceof DOMElement ) ) {
            continue;
        }

        foreach ( iterator_to_array( $inner->childNodes ) as $child ) {
            if ( $child instanceof DOMElement && 'video' === strtolower( $child->tagName ) && shk_elementor_class_contains( $child->getAttribute( 'class' ), 'slide-bg-video' ) ) {
                $inner->removeChild( $child );
            }
        }

        $video = $dom->createElement( 'video' );
        $video->setAttribute( 'class', 'slide-bg-video' );
        $video->setAttribute( 'autoplay', '' );
        $video->setAttribute( 'muted', '' );
        $video->setAttribute( 'loop', '' );
        $video->setAttribute( 'playsinline', '' );
        $video->setAttribute( 'preload', 'metadata' );
        if ( ! empty( $video_map[ $index ]['poster'] ) ) {
            $video->setAttribute( 'poster', (string) $video_map[ $index ]['poster'] );
        }

        $source = $dom->createElement( 'source' );
        $source->setAttribute( 'src', (string) $video_map[ $index ]['url'] );
        $video->appendChild( $source );

        if ( $inner->firstChild ) {
            $inner->insertBefore( $video, $inner->firstChild );
        } else {
            $inner->appendChild( $video );
        }
    }

    $wrapper = $dom->getElementById( 'shk-slides-video-wrap' );
    if ( ! ( $wrapper instanceof DOMElement ) ) {
        libxml_clear_errors();
        libxml_use_internal_errors( $libxml_previous );
        return $content;
    }

    $result = '';
    foreach ( $wrapper->childNodes as $child ) {
        $result .= $dom->saveHTML( $child );
    }

    libxml_clear_errors();
    libxml_use_internal_errors( $libxml_previous );

    return is_string( $result ) && '' !== $result ? $result : $content;
}

function shk_elementor_slides_render_video( $content, $widget ) {
    if ( ! is_string( $content ) || '' === trim( $content ) ) {
        return $content;
    }

    if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'slides' !== $widget->get_name() ) {
        return $content;
    }

    if ( ! method_exists( $widget, 'get_settings_for_display' ) ) {
        return $content;
    }

    $settings = $widget->get_settings_for_display();
    if ( empty( $settings['slides'] ) || ! is_array( $settings['slides'] ) ) {
        return $content;
    }

    $video_map = [];

    // Fallback map from widget-level textarea (always visible in editor).
    $video_map = shk_elementor_parse_video_map_text( (string) ( $settings['shk_slide_videos_map'] ?? '' ) );

    // Repeater-level controls.
    foreach ( array_values( $settings['slides'] ) as $index => $slide ) {
        if ( isset( $video_map[ $index ] ) ) {
            continue;
        }

        $url = shk_elementor_slides_normalize_media_url( $slide['shk_slide_video_url'] ?? '' );
        if ( '' === $url ) {
            continue;
        }
        $poster = shk_elementor_slides_normalize_media_url( $slide['shk_slide_video_poster'] ?? '' );

        $video_map[ $index ] = [
            'url'    => $url,
            'poster' => $poster,
        ];
    }

    if ( empty( $video_map ) ) {
        return $content;
    }

    // Always expose map for frontend JS fallback injection.
    $json_map = wp_json_encode( $video_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $json_map ) || '' === $json_map ) {
        return $content;
    }

    $content .= '<script type="application/json" class="shk-slide-video-map-data">' . esc_html( $json_map ) . '</script>';

    // Remove previously injected nodes, then try server-side inject by real slide number
    // (aria-label like "1 / 3"), so DOM shifts from dynamic tags do not break mapping.
    $content = preg_replace( '~<video[^>]*class="[^"]*slide-bg-video[^"]*"[^>]*>.*?</video>~is', '', $content );
    $content = shk_elementor_inject_video_by_slide_number( $content, $video_map );

    return $content;
}

add_action( 'wp_footer', 'shk_elementor_slides_video_frontend_fix', 99 );
function shk_elementor_slides_video_frontend_fix() {
    ?>
    <script>
    (function(){
      function parseVideoMapFromWidget(widget){
        if (!widget) return null;
        var script = widget.querySelector('.shk-slide-video-map-data');
        if (!script) return null;
        try {
          var data = JSON.parse((script.textContent || '').trim() || '{}');
          return data && typeof data === 'object' ? data : null;
        } catch (e) {
          return null;
        }
      }

      function injectVideosByMap(widget, map){
        if (!widget || !map) return;
        var slides = Array.prototype.slice.call(widget.querySelectorAll('.swiper-slide')).filter(function(slide){
          return !slide.classList.contains('swiper-slide-duplicate');
        });
        var seq = 0;
        slides.forEach(function(slide){
          seq += 1;
          var aria = slide.getAttribute('aria-label') || '';
          var m = aria.match(/^\s*(\d+)\s*\/\s*\d+\s*$/);
          var slideNum = m ? parseInt(m[1], 10) : seq;
          if (!slideNum || slideNum < 1) return;
          var index = slideNum - 1;
          var cfg = map[index];
          if (!cfg || !cfg.url) return;

          var inner = slide.querySelector('.swiper-slide-inner');
          if (!inner) return;

          var existing = inner.querySelector('video.slide-bg-video');
          if (existing && existing.querySelector('source') && existing.querySelector('source').getAttribute('src') === cfg.url) {
            return;
          }
          if (existing) existing.remove();

          var video = document.createElement('video');
          video.className = 'slide-bg-video';
          video.autoplay = true;
          video.muted = true;
          video.loop = true;
          video.playsInline = true;
          video.preload = 'metadata';
          if (cfg.poster) video.setAttribute('poster', cfg.poster);

          var source = document.createElement('source');
          source.src = cfg.url;
          video.appendChild(source);

          inner.insertBefore(video, inner.firstChild);
        });
      }

      function normalizeSlideVideos(root){
        var scope = root || document;

        scope.querySelectorAll('.elementor-widget-slides, .elementor-widget-container').forEach(function(widget){
          var map = parseVideoMapFromWidget(widget);
          if (map) injectVideosByMap(widget, map);
        });

        scope.querySelectorAll('.swiper-slide-inner .slide-bg-video').forEach(function(video){
          var inner = video.closest('.swiper-slide-inner');
          if (!inner) return;
          if (inner.firstElementChild !== video) {
            inner.insertBefore(video, inner.firstChild);
          }
          video.muted = true;
          video.loop = true;
          video.playsInline = true;
          var playPromise = video.play && video.play();
          if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function(){});
          }
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ normalizeSlideVideos(document); });
      } else {
        normalizeSlideVideos(document);
      }

      var mo = new MutationObserver(function(mutations){
        mutations.forEach(function(m){
          if (!m.addedNodes) return;
          m.addedNodes.forEach(function(node){
            if (!(node instanceof Element)) return;
            if (
              node.matches('.swiper-slide-inner, .swiper-slide, .elementor-widget-slides, .elementor-widget-container') ||
              node.querySelector('.slide-bg-video, .shk-slide-video-map-data')
            ) {
              normalizeSlideVideos(node);
            }
          });
        });
      });
      mo.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}
