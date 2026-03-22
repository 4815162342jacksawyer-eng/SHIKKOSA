<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function shk_parser_storage_dir() {
    return trailingslashit( get_stylesheet_directory() ) . 'shk-parser-data';
}

function shk_parser_data_dir() {
    return shk_parser_storage_dir();
}

function shk_parser_local_runs_dir() {
    return trailingslashit( shk_parser_data_dir() ) . 'local-runs';
}

function shk_parser_local_run_dir( $run_id ) {
    return trailingslashit( shk_parser_local_runs_dir() ) . sanitize_text_field( (string) $run_id );
}

function shk_parser_local_run_exports_dir( $run_id ) {
    return trailingslashit( shk_parser_local_run_dir( $run_id ) ) . 'exports';
}

function shk_parser_remote_enabled() {
    return false;
}

function shk_parser_remote_base_url_option_name() {
    return 'shk_parser_remote_base_url';
}

function shk_parser_normalize_remote_base_url( $raw ) {
    $raw = trim( (string) $raw );
    if ( '' === $raw ) {
        return '';
    }

    // Accept host:port input and normalize accidental leading slashes.
    $raw = ltrim( $raw, '/' );
    if ( ! preg_match( '~^https?://~i', $raw ) ) {
        $raw = 'http://' . $raw;
    }

    $url = esc_url_raw( $raw );
    if ( '' === $url ) {
        return '';
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
        return '';
    }

    return untrailingslashit( $url );
}

function shk_parser_remote_base_url() {
    return '';
}

function shk_parser_remote_cache_dir() {
    return trailingslashit( shk_parser_data_dir() ) . 'remote-cache';
}

function shk_parser_remote_cache_run_dir( $run_id ) {
    return trailingslashit( shk_parser_remote_cache_dir() ) . sanitize_text_field( (string) $run_id );
}

function shk_parser_read_json_file( $path, $default = [] ) {
    if ( ! $path || ! file_exists( $path ) ) {
        return $default;
    }

    $raw = file_get_contents( $path );
    if ( false === $raw || '' === $raw ) {
        return $default;
    }

    $decoded = json_decode( $raw, true );
    return is_array( $decoded ) ? $decoded : $default;
}

function shk_parser_build_run_files_map( $exports_dir ) {
    $exports_dir = trailingslashit( (string) $exports_dir );
    return [
        'summary'        => file_exists( $exports_dir . 'summary.json' ) ? $exports_dir . 'summary.json' : '',
        'categories'     => file_exists( $exports_dir . 'categories.csv' ) ? $exports_dir . 'categories.csv' : '',
        'collections'    => file_exists( $exports_dir . 'collections.csv' ) ? $exports_dir . 'collections.csv' : '',
        'products'       => file_exists( $exports_dir . 'products.csv' ) ? $exports_dir . 'products.csv' : '',
        'media'          => file_exists( $exports_dir . 'media.csv' ) ? $exports_dir . 'media.csv' : '',
        'related'        => file_exists( $exports_dir . 'related.csv' ) ? $exports_dir . 'related.csv' : '',
        'color_families' => file_exists( $exports_dir . 'color_families.csv' ) ? $exports_dir . 'color_families.csv' : '',
        'woo_products'   => file_exists( $exports_dir . 'woo_products.csv' ) ? $exports_dir . 'woo_products.csv' : '',
    ];
}

function shk_parser_normalize_run_summary( array $summary, $exports_dir, $source = 'local' ) {
    return [
        'run_id'             => (string) ( $summary['run_id'] ?? '' ),
        'mode'               => (string) ( $summary['mode'] ?? '' ),
        'created_at'         => (string) ( $summary['created_at'] ?? '' ),
        'updated_at'         => (string) ( $summary['updated_at'] ?? '' ),
        'generated_at'       => (string) ( $summary['generated_at'] ?? ( $summary['updated_at'] ?? ( $summary['created_at'] ?? '' ) ) ),
        'seed_category_slug' => (string) ( $summary['seed_category_slug'] ?? '' ),
        'seed_category_name' => (string) ( $summary['seed_category_name'] ?? '' ),
        'products'           => (int) ( $summary['products'] ?? 0 ),
        'categories'         => (int) ( $summary['categories'] ?? 0 ),
        'collections'        => (int) ( $summary['collections'] ?? 0 ),
        'selected_count'     => (int) ( $summary['selected_count'] ?? 0 ),
        'media_loaded'       => (int) ( $summary['media_loaded'] ?? 0 ),
        'color_families'     => (int) ( $summary['color_families'] ?? 0 ),
        'path'               => trailingslashit( dirname( untrailingslashit( (string) $exports_dir ) ) ),
        'source'             => $source,
        'logs'               => isset( $summary['logs'] ) && is_array( $summary['logs'] ) ? $summary['logs'] : [],
        'files'              => shk_parser_build_run_files_map( $exports_dir ),
    ];
}

function shk_parser_get_local_run( $run_id ) {
    $run_id = sanitize_text_field( (string) $run_id );
    if ( '' === $run_id ) {
        return [];
    }

    $exports_dir = shk_parser_local_run_exports_dir( $run_id );
    $summary_path = trailingslashit( $exports_dir ) . 'summary.json';
    $summary = shk_parser_read_json_file( $summary_path, [] );
    if ( empty( $summary ) ) {
        return [];
    }

    if ( empty( $summary['run_id'] ) ) {
        $summary['run_id'] = $run_id;
    }

    return shk_parser_normalize_run_summary( $summary, $exports_dir, 'local' );
}

function shk_parser_list_local_runs() {
    $dir = shk_parser_local_runs_dir();
    if ( ! is_dir( $dir ) ) {
        return [];
    }

    $runs = [];
    foreach ( glob( trailingslashit( $dir ) . '*/exports/summary.json' ) as $summary_path ) {
        $summary = shk_parser_read_json_file( $summary_path, [] );
        if ( empty( $summary ) ) {
            continue;
        }

        $run_id = (string) ( $summary['run_id'] ?? basename( dirname( dirname( $summary_path ) ) ) );
        if ( '' === $run_id ) {
            continue;
        }

        $runs[] = shk_parser_normalize_run_summary(
            $summary,
            dirname( $summary_path ),
            'local'
        );
    }

    usort(
        $runs,
        static function ( $a, $b ) {
            return strcmp( (string) ( $b['generated_at'] ?? '' ), (string) ( $a['generated_at'] ?? '' ) );
        }
    );

    return $runs;
}

function shk_parser_remote_request( $path, $method = 'GET', $body = null, $timeout = 60 ) {
    return new WP_Error( 'remote_parser_disabled', 'Remote parser отключен в этом режиме.' );
}

function shk_parser_remote_get_state() {
    return [];
}

function shk_parser_admin_read_csv_assoc( $path ) {
    if ( ! $path || ! file_exists( $path ) ) {
        return [];
    }

    $rows = [];
    $handle = fopen( $path, 'r' );
    if ( ! $handle ) {
        return [];
    }

    $headers = fgetcsv( $handle );
    if ( ! is_array( $headers ) ) {
        fclose( $handle );
        return [];
    }

    $headers = array_map(
        static function ( $header ) {
            $header = (string) $header;
            $header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
            return trim( $header );
        },
        $headers
    );

    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $assoc = [];
        foreach ( $headers as $index => $header ) {
            $assoc[ $header ] = isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
        }
        $rows[] = $assoc;
    }

    fclose( $handle );
    return $rows;
}

function shk_parser_admin_pick_row_value( array $row, array $candidates, $default = '' ) {
    foreach ( $candidates as $key ) {
        if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
            return trim( (string) $row[ $key ] );
        }
    }
    return $default;
}

function shk_parser_build_overview_from_run( array $run ) {
    $files = isset( $run['files'] ) && is_array( $run['files'] ) ? $run['files'] : [];
    $categories_path = (string) ( $files['categories'] ?? '' );
    $collections_path = (string) ( $files['collections'] ?? '' );
    $products_path = (string) ( $files['products'] ?? '' );

    $categories = [];
    foreach ( shk_parser_admin_read_csv_assoc( $categories_path ) as $row ) {
        $slug = shk_parser_admin_pick_row_value( $row, [ 'slug', 'category_slug' ] );
        $name = shk_parser_admin_pick_row_value( $row, [ 'name', 'category_name', 'title' ] );
        if ( '' === $slug && '' === $name ) {
            continue;
        }

        $product_count = (int) shk_parser_admin_pick_row_value(
            $row,
            [ 'product_count', 'products_count', 'products', 'count', 'total' ],
            '0'
        );

        $categories[] = [
            'slug'         => $slug,
            'name'         => $name ?: $slug,
            'product_count'=> $product_count,
            'description'  => shk_parser_admin_pick_row_value( $row, [ 'description', 'desc' ] ),
        ];
    }

    $collections = [];
    foreach ( shk_parser_admin_read_csv_assoc( $collections_path ) as $row ) {
        $collections[] = [
            'slug' => shk_parser_admin_pick_row_value( $row, [ 'slug', 'collection_slug' ] ),
            'name' => shk_parser_admin_pick_row_value( $row, [ 'name', 'collection_name', 'title' ] ),
        ];
    }

    $catalog_products = (int) ( $run['products'] ?? 0 );
    if ( 0 === $catalog_products && $products_path && file_exists( $products_path ) ) {
        $catalog_products = count( shk_parser_admin_read_csv_assoc( $products_path ) );
    }

    if ( empty( $categories ) && empty( $collections ) && 0 === $catalog_products ) {
        return [];
    }

    return [
        'generated_at'    => (string) ( $run['generated_at'] ?? ( $run['updated_at'] ?? '' ) ),
        'categories'      => $categories,
        'collections'     => $collections,
        'catalog_products'=> $catalog_products,
    ];
}

function shk_parser_get_overview_data() {
    foreach ( shk_parser_list_local_runs() as $run ) {
        $overview = shk_parser_build_overview_from_run( $run );
        if ( ! empty( $overview ) ) {
            return $overview;
        }
    }

    return [];
}

function shk_parser_remote_download_run_file( $run_id, $name ) {
    return new WP_Error( 'remote_file_disabled', 'Remote parser отключен. Используйте локальный run ZIP.' );
}

function shk_parser_python_checks() {
    return [
        'python_bin'          => '',
        'system_python_bin'   => 'disabled',
        'vendor_dir'          => '',
        'vendor_exists'       => false,
        'requirements_exists' => false,
        'core_exists'         => false,
        'deps_ok'             => false,
        'deps_message'        => 'remote parser disabled',
    ];
}

function shk_parser_get_current_job() {
    return [];
}

function shk_parser_start_background_job( $title, array $args ) {
    return new WP_Error( 'remote_disabled', 'Remote parser отключен.' );
}

function shk_parser_start_selected_background_job( $title, array $slugs, $include_media = false ) {
    return new WP_Error( 'remote_disabled', 'Remote parser отключен.' );
}

function shk_parser_list_runs() {
    return shk_parser_list_local_runs();
}

function shk_parser_get_run( $run_id ) {
    $run_id = sanitize_text_field( (string) $run_id );
    if ( '' === $run_id ) {
        return [];
    }

    $local = shk_parser_get_local_run( $run_id );
    if ( ! empty( $local ) ) {
        return $local;
    }

    return [];
}

add_action( 'admin_menu', 'shk_parser_register_admin_page' );
function shk_parser_register_admin_page() {
    add_menu_page(
        'SHIKKOSA Parser',
        'SHIKKOSA Parser',
        'manage_options',
        'shk-parser',
        'shk_parser_render_admin_page',
        'dashicons-database-import',
        58
    );
}

add_action( 'wp_ajax_shk_parser_state', 'shk_parser_ajax_state' );
function shk_parser_ajax_state() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }

    $payload = [
        'overview' => shk_parser_get_overview_data(),
        'runs'     => shk_parser_list_runs(),
    ];

    wp_send_json_success( apply_filters( 'shk_parser_state_payload', $payload ) );
}

function shk_parser_remove_dir_recursive( $path ) {
    if ( ! $path || ! file_exists( $path ) ) {
        return;
    }

    if ( is_file( $path ) ) {
        @unlink( $path );
        return;
    }

    foreach ( glob( trailingslashit( $path ) . '*' ) as $item ) {
        shk_parser_remove_dir_recursive( $item );
    }

    @rmdir( $path );
}

add_action( 'wp_ajax_shk_parser_upload_run_zip', 'shk_parser_ajax_upload_run_zip' );
function shk_parser_ajax_upload_run_zip() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }

    check_ajax_referer( 'shk_parser_nonce', 'nonce' );

    if ( empty( $_FILES['run_zip']['tmp_name'] ) ) {
        wp_send_json_error( [ 'message' => 'ZIP-файл не передан.' ], 400 );
    }

    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_send_json_error( [ 'message' => 'На сервере недоступен ZipArchive.' ], 500 );
    }

    $tmp_zip = $_FILES['run_zip']['tmp_name'];
    $work_dir = trailingslashit( shk_parser_data_dir() ) . 'tmp-upload-' . wp_generate_uuid4();
    $extract_dir = trailingslashit( $work_dir ) . 'exports';
    wp_mkdir_p( $extract_dir );

    $zip = new ZipArchive();
    $opened = $zip->open( $tmp_zip );
    if ( true !== $opened ) {
        shk_parser_remove_dir_recursive( $work_dir );
        wp_send_json_error( [ 'message' => 'Не удалось открыть ZIP-архив.' ], 400 );
    }

    if ( ! $zip->extractTo( $extract_dir ) ) {
        $zip->close();
        shk_parser_remove_dir_recursive( $work_dir );
        wp_send_json_error( [ 'message' => 'Не удалось распаковать ZIP-архив.' ], 400 );
    }
    $zip->close();

    $summary_path = trailingslashit( $extract_dir ) . 'summary.json';
    $products_path = trailingslashit( $extract_dir ) . 'products.csv';
    $summary = shk_parser_read_json_file( $summary_path, [] );

    if ( empty( $summary ) || empty( $summary['run_id'] ) || ! file_exists( $products_path ) ) {
        shk_parser_remove_dir_recursive( $work_dir );
        wp_send_json_error( [ 'message' => 'В архиве не найдены обязательные summary.json / products.csv.' ], 400 );
    }

    $run_id = sanitize_text_field( (string) $summary['run_id'] );
    $target_dir = shk_parser_local_run_dir( $run_id );
    shk_parser_remove_dir_recursive( $target_dir );
    wp_mkdir_p( dirname( $target_dir ) );
    rename( $work_dir, $target_dir );

    $run = shk_parser_get_local_run( $run_id );
    if ( empty( $run ) ) {
        wp_send_json_error( [ 'message' => 'Архив загружен, но run не удалось зарегистрировать.' ], 500 );
    }

    wp_send_json_success(
        [
            'message' => 'Run ZIP загружен.',
            'run_id'  => $run_id,
            'run'     => $run,
        ]
    );
}


add_action( 'wp_ajax_shk_parser_get_run', 'shk_parser_ajax_get_run' );
function shk_parser_ajax_get_run() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостаточно прав.' ], 403 );
    }

    $run_id = isset( $_GET['run_id'] ) ? sanitize_text_field( wp_unslash( $_GET['run_id'] ) ) : '';
    if ( '' === $run_id ) {
        wp_send_json_error( [ 'message' => 'Не передан run_id.' ], 400 );
    }

    $run = shk_parser_get_run( $run_id );
    if ( empty( $run ) ) {
        wp_send_json_error( [ 'message' => 'Run не найден.' ], 404 );
    }

    wp_send_json_success( [ 'run' => $run ] );
}

function shk_parser_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $nonce = wp_create_nonce( 'shk_parser_nonce' );
    ?>
    <div class="wrap shk-parser-admin">
        <h1>SHIKKOSA Parser</h1>
        <p>Локальный режим: загрузка run ZIP и импорт в Woo без удаленного parser-сервиса.</p>

        <div id="shk-parser-root" data-nonce="<?php echo esc_attr( $nonce ); ?>"></div>

        <style>
            .shk-parser-admin { max-width: 1400px; }
            .shk-parser-grid { display:grid; gap:16px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top:20px; }
            .shk-parser-card { background:#fff; border:1px solid #e3e3e3; border-radius:16px; padding:18px; box-shadow:0 4px 18px rgba(0,0,0,.04); }
            .shk-parser-card h2, .shk-parser-card h3 { margin-top:0; }
            .shk-parser-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
            .shk-parser-actions select, .shk-parser-actions button { min-height:40px; }
            .shk-parser-wide { margin-top:20px; }
            .shk-parser-table-wrap { overflow:auto; border:1px solid #e3e3e3; border-radius:16px; background:#fff; }
            .shk-parser-table { width:100%; border-collapse:collapse; min-width:900px; }
            .shk-parser-table th, .shk-parser-table td { padding:12px 14px; border-bottom:1px solid #f0f0f0; text-align:left; vertical-align:top; }
            .shk-parser-table th { background:#faf7f3; }
            .shk-parser-status { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; border:1px solid #ddd; background:#fff; }
            .shk-parser-muted { color:#666; }
            .shk-parser-log { background:#fff; border:1px solid #e3e3e3; border-radius:16px; padding:14px; white-space:pre-wrap; max-height:320px; overflow:auto; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; }
            .shk-parser-pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#f4e9ee; color:#a03b67; }
            .shk-parser-run-highlight { border:1px solid #ead7bf; background:#fff9f1; border-radius:12px; padding:12px 14px; margin-top:12px; }
            .shk-parser-busy { opacity:.65; pointer-events:none; }
            @media (max-width: 1100px) { .shk-parser-grid { grid-template-columns:1fr; } }
        </style>

        <script>
        (function() {
            const root = document.getElementById('shk-parser-root');
            const nonce = root.dataset.nonce;
            const ajaxUrl = window.ajaxurl;
            let importTickBusy = false;
            let actionBusy = false;

            root.innerHTML = `
                <div class="shk-parser-grid">
                    <div class="shk-parser-card">
                        <h2>Ручной импорт ZIP</h2>
                        <div class="shk-parser-muted">Загрузи ZIP-архив run. После загрузки run появится в списке ниже и станет доступен для Woo-импорта.</div>
                        <div class="shk-parser-actions">
                            <input type="file" id="shk-parser-run-zip" accept=".zip,application/zip">
                            <button class="button button-primary" id="shk-parser-upload-run-zip">Загрузить run ZIP</button>
                        </div>
                        <div id="shk-parser-upload-note" class="shk-parser-muted" style="margin-top:10px;"></div>
                    </div>
                    <div class="shk-parser-card">
                        <h2>Локальные run</h2>
                        <div class="shk-parser-muted">Выбери run из локального списка и запускай импорт в Woo.</div>
                        <div class="shk-parser-actions">
                            <select id="shk-parser-run-select" style="min-width:280px;"></select>
                            <button class="button" id="shk-parser-import-products-by-select">Карточки в Woo</button>
                            <button class="button" id="shk-parser-import-media-by-select">Медиа в Woo</button>
                            <button class="button" id="shk-parser-import-media-broken-by-select">Только проблемные (медиа)</button>
                        </div>
                        <div id="shk-parser-overview-meta" class="shk-parser-muted" style="margin-top:12px;"></div>
                        <div id="shk-parser-last-run" class="shk-parser-run-highlight" style="display:none;"></div>
                    </div>
                    <div class="shk-parser-card">
                        <h2>Существующие товары Woo</h2>
                        <div id="shk-parser-existing-meta" class="shk-parser-muted"></div>
                        <div class="shk-parser-actions">
                            <button class="button" id="shk-parser-bind-existing">Привязать source_slug по slug</button>
                            <button class="button" id="shk-parser-repair-family-links">Repair связки color family</button>
                            <button class="button button-secondary" id="shk-parser-media-cleanup">Удалить дубли медиа</button>
                        </div>
                    </div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Текущий Woo-импорт</h2>
                    <div id="shk-parser-import-job"></div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Сопоставление категорий</h2>
                    <div class="shk-parser-actions">
                        <button class="button" id="shk-parser-map-save">Сохранить сопоставления</button>
                        <button class="button button-primary" id="shk-parser-map-auto">Создать/сопоставить 1:1</button>
                    </div>
                    <div class="shk-parser-table-wrap" style="margin-top:12px;"><table class="shk-parser-table" id="shk-parser-mapping"></table></div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Категории донора</h2>
                    <div class="shk-parser-table-wrap"><table class="shk-parser-table" id="shk-parser-categories"></table></div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Последние прогоны</h2>
                    <div class="shk-parser-table-wrap"><table class="shk-parser-table" id="shk-parser-runs"></table></div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Проблемные/непривязанные товары</h2>
                    <div class="shk-parser-table-wrap"><table class="shk-parser-table" id="shk-parser-existing-table"></table></div>
                </div>

                <div class="shk-parser-card shk-parser-wide">
                    <h2>Лог run</h2>
                    <div id="shk-parser-run-log" class="shk-parser-log">Выберите run.</div>
                </div>
            `;

            async function request(action, payload = {}, method = 'POST') {
                const data = new URLSearchParams();
                data.append('action', action);
                if (method === 'POST') data.append('nonce', nonce);
                Object.entries(payload).forEach(([key, value]) => data.append(key, value));
                const response = await fetch(ajaxUrl + (method === 'GET' ? '?' + data.toString() : ''), {
                    method,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: method === 'POST' ? data.toString() : undefined
                });
                return await response.json();
            }

            function setBusy(flag) {
                actionBusy = flag;
                root.classList.toggle('shk-parser-busy', flag);
                root.querySelectorAll('button, select').forEach(el => {
                    if (el.dataset.runLog) {
                        el.disabled = false;
                        return;
                    }
                    el.disabled = !!flag;
                });
            }

            async function runImport(kind, payload = {}) {
                if (actionBusy) return;
                setBusy(true);
                try {
                    let action = 'shk_parser_start_import_products';
                    if (kind === 'media') action = 'shk_parser_start_import_media';
                    if (kind === 'media_broken') action = 'shk_parser_start_import_media_broken';
                    if (kind === 'media_cleanup') action = 'shk_parser_start_media_cleanup';
                    const result = await request(action, payload, 'POST');
                    if (!result.success) alert(result.data?.message || 'Ошибка импорта');
                    await refreshState();
                } finally {
                    setBusy(false);
                }
            }

            async function stopImportJob() {
                if (actionBusy) return;
                if (!confirm('Остановить текущую Woo-задачу импорта?')) return;
                setBusy(true);
                try {
                    const result = await request('shk_parser_stop_import_job', {}, 'POST');
                    if (!result.success) alert(result.data?.message || 'Не удалось остановить Woo-импорт');
                    await refreshState();
                } finally {
                    setBusy(false);
                }
            }

            function renderImportJob(job) {
                const el = document.getElementById('shk-parser-import-job');
                if (!job || !job.title) {
                    el.innerHTML = '<span class="shk-parser-status">Нет активной Woo-задачи</span>';
                    return;
                }
                let html = `<div class="shk-parser-status"><strong>${escapeHtml(job.title)}</strong> · ${escapeHtml(job.status || 'unknown')}</div>`;
                if ((job.status || '') === 'running') {
                    html += `<div class="shk-parser-actions" style="margin-top:10px;"><button class="button button-secondary" data-action="stop-import">Остановить Woo-импорт</button></div>`;
                }
                const isMedia = (job.type || '') === 'media';
                const isMediaCleanup = (job.type || '') === 'media_cleanup';
                const metrics = isMediaCleanup
                    ? `изменено карточек: ${job.cleanup_products_changed || 0} · убрано дублей-ссылок: ${job.cleanup_links_removed || 0} · удалено вложений: ${job.cleanup_attachments_deleted || 0}`
                    : (isMedia
                    ? `успешных строк: ${job.media_rows_ok || 0} · фото: ${job.media_photos_added || 0} · видео: ${job.media_videos_linked || 0} · удалено: ${job.media_stale_removed || 0}`
                    : `создано: ${job.created || 0} · обновлено: ${job.updated || 0}`);
                html += `<div class="shk-parser-muted" style="margin-top:10px;">run: ${escapeHtml(job.run_id || '—')} · обработано: ${job.processed || 0}/${job.total || 0} · ${metrics} · ошибок: ${job.failed || 0}</div>`;
                if (Array.isArray(job.logs) && job.logs.length) {
                    html += `<div class="shk-parser-log" style="margin-top:12px; max-height:180px;">${escapeHtml(job.logs.join("\n"))}</div>`;
                }
                el.innerHTML = html;
            }

            function renderOverview(overview) {
                const meta = document.getElementById('shk-parser-overview-meta');
                if (!overview || !overview.generated_at) {
                    meta.textContent = 'Обзор недоступен или ещё не собран. Для ручного сценария это не мешает загружать ZIP и импортировать run.';
                    return;
                }
                meta.textContent = `Обновлено: ${overview.generated_at} · категорий: ${overview.categories.length} · коллекций: ${overview.collections.length} · карточек на /catalog: ${overview.catalog_products}`;

                const table = document.getElementById('shk-parser-categories');
                table.innerHTML = `
                    <thead><tr><th>Категория</th><th>Slug</th><th>Товаров</th><th>Описание</th></tr></thead>
                    <tbody>${overview.categories.map(c => `<tr><td>${escapeHtml(c.name || '')}</td><td><code>${escapeHtml(c.slug || '')}</code></td><td>${c.product_count || 0}</td><td>${escapeHtml(c.description || '')}</td></tr>`).join('')}</tbody>`;
            }

            function renderLastRun(runs) {
                const el = document.getElementById('shk-parser-last-run');
                if (!Array.isArray(runs) || !runs.length) {
                    el.style.display = 'none';
                    el.innerHTML = '';
                    return;
                }
                const run = runs[0];
                el.style.display = 'block';
                el.innerHTML = `<strong>Последний run:</strong> ${escapeHtml(run.run_id || '')} · ${escapeHtml(run.mode || '')} · товаров: ${run.products || 0} · медиа: ${run.media_loaded || 0} · источник: ${escapeHtml(run.source || 'local')}`;
            }

            function renderRuns(runs) {
                const runSelect = document.getElementById('shk-parser-run-select');
                runSelect.innerHTML = '<option value="">Выберите run</option>' + runs.map(r => `<option value="${r.run_id}">${r.run_id} · ${r.mode} · ${r.products} товаров</option>`).join('');
                renderLastRun(runs);

                const table = document.getElementById('shk-parser-runs');
                table.innerHTML = `
                    <thead><tr><th>Run</th><th>Источник</th><th>Режим</th><th>Категория</th><th>Товары</th><th>Связки цветов</th><th>Медиа</th><th>Обновлен</th><th>Действия</th></tr></thead>
                    <tbody>${runs.map((r, index) => `<tr${index === 0 ? ' style="background:#fff9f1;"' : ''}>
                        <td><span class="shk-parser-pill">${escapeHtml(r.run_id || '')}</span></td>
                        <td>${escapeHtml(r.source || 'remote')}</td>
                        <td>${escapeHtml(r.mode || '')}</td>
                        <td>${escapeHtml(r.seed_category_name || r.seed_category_slug || '—')}</td>
                        <td>${r.products || 0}</td>
                        <td>${r.color_families || 0}</td>
                        <td>${r.media_loaded || 0}</td>
                        <td>${escapeHtml(r.generated_at || '')}</td>
                        <td>
                            <button class="button" data-run-import-products="${escapeHtml(r.run_id || '')}">Карточки в Woo</button>
                            <button class="button" data-run-import-media="${escapeHtml(r.run_id || '')}">Медиа в Woo</button>
                            <button class="button" data-run-log="${escapeHtml(r.run_id || '')}">Лог</button>
                        </td>
                    </tr>`).join('')}</tbody>`;
            }

            function renderMappings(overview, wooCategories, categoryMap) {
                const table = document.getElementById('shk-parser-mapping');
                const categories = Array.isArray(overview?.categories) ? overview.categories : [];
                if (!categories.length) {
                    table.innerHTML = '<thead><tr><th>Донор</th><th>Woo</th></tr></thead><tbody><tr><td colspan="2">Сначала обновите обзор.</td></tr></tbody>';
                    return;
                }

                const options = ['<option value="">Не сопоставлено</option>'].concat(
                    (wooCategories || []).map(cat => `<option value="${cat.term_id}">${escapeHtml(cat.name)} (${escapeHtml(cat.slug)})</option>`)
                ).join('');

                table.innerHTML = `
                    <thead><tr><th>Категория донора</th><th>Slug</th><th>Товаров</th><th>Категория Woo</th></tr></thead>
                    <tbody>${categories.map(cat => {
                        const selected = String((categoryMap && categoryMap[cat.slug]) || '');
                        return `<tr>
                            <td>${escapeHtml(cat.name || '')}</td>
                            <td><code>${escapeHtml(cat.slug || '')}</code></td>
                            <td>${cat.product_count || 0}</td>
                            <td>
                                <select data-map-slug="${escapeHtml(cat.slug || '')}" data-selected="${selected}" style="min-width:280px;">
                                    ${options}
                                </select>
                            </td>
                        </tr>`;
                    }).join('')}</tbody>`;

                table.querySelectorAll('[data-map-slug]').forEach(select => {
                    const selected = select.dataset.selected || '';
                    if (selected) select.value = selected;
                });
            }

            function renderExisting(existing) {
                const meta = document.getElementById('shk-parser-existing-meta');
                const table = document.getElementById('shk-parser-existing-table');
                if (!existing || !existing.total) {
                    meta.textContent = 'Товары Woo ещё не проанализированы.';
                    table.innerHTML = '<thead><tr><th>Товар</th><th>Slug</th><th>Source</th><th>Проблемы</th></tr></thead><tbody><tr><td colspan="4">Нет данных.</td></tr></tbody>';
                    return;
                }

                meta.textContent = `Всего: ${existing.total} · привязано: ${existing.bound} · без source_slug: ${existing.unbound} · без featured: ${existing.missing_featured} · без gallery: ${existing.missing_gallery} · кандидатов на repair: ${existing.repair_candidates}`;
                const samples = Array.isArray(existing.samples) ? existing.samples : [];
                table.innerHTML = `
                    <thead><tr><th>Товар</th><th>Slug WP</th><th>Source slug</th><th>Проблемы</th></tr></thead>
                    <tbody>${samples.length ? samples.map(item => `<tr>
                        <td><a href="${ajaxUrl.replace('admin-ajax.php', 'post.php?post=' + item.product_id + '&action=edit')}">${escapeHtml(item.name || '')}</a></td>
                        <td><code>${escapeHtml(item.slug || '')}</code></td>
                        <td><code>${escapeHtml(item.source_slug || item.candidate_slug || '')}</code></td>
                        <td>${[
                            item.source_slug ? '' : 'нет source_slug',
                            item.missing_featured ? 'нет featured' : '',
                            item.missing_gallery ? 'нет gallery' : ''
                        ].filter(Boolean).join(' · ')}</td>
                    </tr>`).join('') : `<tr><td colspan="4">Проблемные товары не найдены.</td></tr>`}</tbody>`;
            }

            async function loadRunLog(runId) {
                const result = await request('shk_parser_get_run', { run_id: runId }, 'GET');
                if (!result.success) return;
                const logs = result.data?.run?.logs || [];
                document.getElementById('shk-parser-run-log').textContent = logs.length ? logs.join("\n") : 'Лог пуст';
            }

            function escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;');
            }

            function collectMappings() {
                const out = {};
                document.querySelectorAll('[data-map-slug]').forEach(select => {
                    out[select.dataset.mapSlug] = select.value ? Number(select.value) : 0;
                });
                return out;
            }

            async function saveMappings() {
                const result = await request('shk_parser_save_category_map', {
                    mapping_json: JSON.stringify(collectMappings())
                }, 'POST');
                if (!result.success) alert(result.data?.message || 'Ошибка сохранения сопоставлений');
                await refreshState();
            }

            async function autoMapCategories() {
                const result = await request('shk_parser_auto_map_categories', {}, 'POST');
                if (!result.success) alert(result.data?.message || 'Ошибка автосопоставления');
                await refreshState();
            }

            async function bindExistingProducts() {
                const result = await request('shk_parser_bind_existing_products', {}, 'POST');
                if (!result.success) alert(result.data?.message || 'Ошибка привязки существующих товаров');
                await refreshState();
            }

            async function repairFamilyLinks() {
                const result = await request('shk_parser_repair_family_links', {}, 'POST');
                if (!result.success) {
                    alert(result.data?.message || 'Ошибка repair связок');
                    return;
                }
                const r = result.data?.repair || {};
                alert(
                    `Repair завершен.\n` +
                    `Проверено: ${r.scanned || 0}\n` +
                    `Привязано source_slug: ${r.source_bound || 0}\n` +
                    `Обновлено related_ids: ${r.related_updated || 0}\n` +
                    `Заполнено color_family_members: ${r.members_filled || 0}\n` +
                    `Синхронизировано upsell: ${r.upsell_synced || 0}`
                );
                await refreshState();
            }

            async function uploadRunZip() {
                const input = document.getElementById('shk-parser-run-zip');
                const note = document.getElementById('shk-parser-upload-note');
                if (!input.files || !input.files.length) {
                    alert('Выберите ZIP-файл run');
                    return;
                }

                const form = new FormData();
                form.append('action', 'shk_parser_upload_run_zip');
                form.append('nonce', nonce);
                form.append('run_zip', input.files[0]);

                note.textContent = 'Загружаю и распаковываю архив...';
                const response = await fetch(ajaxUrl, { method: 'POST', body: form });
                const result = await response.json();
                if (!result.success) {
                    note.textContent = '';
                    alert(result.data?.message || 'Не удалось загрузить ZIP');
                    return;
                }

                note.textContent = (result.data?.message || 'ZIP загружен') + (result.data?.run_id ? ` · run_id: ${result.data.run_id}` : '');
                input.value = '';
                await refreshState();
            }

            async function tickImport() {
                if (importTickBusy) return;
                importTickBusy = true;
                try {
                    await request('shk_parser_import_tick', {}, 'POST');
                } finally {
                    importTickBusy = false;
                }
            }

            async function refreshState() {
                const result = await request('shk_parser_state', {}, 'GET');
                if (!result.success) return;
                const data = result.data || {};
                renderImportJob(data.import_job || {});
                renderOverview(data.overview || {});
                renderMappings(data.overview || {}, data.woo_categories || [], data.category_map || {});
                renderExisting(data.existing_products || {});
                renderRuns(data.runs || []);
                if (data.import_job && data.import_job.status === 'running') {
                    setTimeout(() => {
                        tickImport().then(refreshState);
                    }, 600);
                }
            }

            root.addEventListener('click', async (event) => {
                const button = event.target.closest('button');
                if (!button) return;
                if (actionBusy && !button.dataset.runLog) return;

                if (button.dataset.action === 'stop-import') return stopImportJob();
                if (button.dataset.runImportProducts) return runImport('products', { run_id: button.dataset.runImportProducts });
                if (button.dataset.runImportMedia) return runImport('media', { run_id: button.dataset.runImportMedia });
                if (button.dataset.runLog) return loadRunLog(button.dataset.runLog);
            });

            document.getElementById('shk-parser-import-products-by-select').addEventListener('click', () => {
                const run_id = document.getElementById('shk-parser-run-select').value;
                if (!run_id) return alert('Выберите run');
                runImport('products', { run_id });
            });

            document.getElementById('shk-parser-import-media-by-select').addEventListener('click', () => {
                const run_id = document.getElementById('shk-parser-run-select').value;
                if (!run_id) return alert('Выберите run');
                runImport('media', { run_id });
            });

            document.getElementById('shk-parser-import-media-broken-by-select').addEventListener('click', () => {
                const run_id = document.getElementById('shk-parser-run-select').value;
                if (!run_id) return alert('Выберите run');
                runImport('media_broken', { run_id });
            });

            document.getElementById('shk-parser-map-save').addEventListener('click', saveMappings);
            document.getElementById('shk-parser-map-auto').addEventListener('click', autoMapCategories);
            document.getElementById('shk-parser-bind-existing').addEventListener('click', bindExistingProducts);
            document.getElementById('shk-parser-repair-family-links').addEventListener('click', repairFamilyLinks);
            document.getElementById('shk-parser-media-cleanup').addEventListener('click', () => runImport('media_cleanup'));
            document.getElementById('shk-parser-upload-run-zip').addEventListener('click', uploadRunZip);

            refreshState();
            setInterval(refreshState, 5000);
        })();
        </script>
    </div>
    <?php
}
