<?php

/**
 * Registers a custom REST API route for retrieving media.
 *
 * This endpoint allows users to retrieve a list of media items from the WordPress Media Library via a GET request.
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/get-media', [
        'methods' => 'GET',
        'callback' => 'get_media',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Retrieves a list of media items from the Media Library.
 *
 * This function processes the GET request to retrieve a paginated list of media items. It returns essential details
 * for each media item, such as ID, URL, title, MIME type, file format, alt text, and caption.
 *
 * @param WP_REST_Request $request The incoming API request.
 */
function get_media($request)
{
    $per_page = $request->get_param('size');
    $raw_page = $request->get_param('page');
    $media_id = $request->get_param('id');
    $search_name = $request->get_param('name');
    $batch_size = 500;

    if ($media_id !== null) {
        $single_media = get_post($media_id);

        if (!$single_media || $single_media->post_type !== 'attachment') {
            return new WP_Error('media_not_found', 'Media not found', ['status' => 404]);
        }

        $response = get_media_response_data($single_media);

        return new WP_REST_Response($response, 200);
    }

    $page = isset($raw_page) ? max(0, intval($raw_page)) : 0;

    if ($per_page === null) {
        $per_page = 10;
    } elseif (intval($per_page) === 0) {
        return new WP_Error('invalid_size', 'Invalid size, please check!', ['status' => 400]);
    } else {
        $per_page = intval($per_page);
    }

    $query_args = [
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'orderby' => 'date',
        'order' => 'DESC',
        'post_mime_type' => array('image/jpeg', 'image/jpg', 'image/png'),
        's' => $search_name
    ];

    $paged = 1;
    $total_items = 0;

    do {
        $query_args['posts_per_page'] = $batch_size;
        $query_args['paged'] = $paged;
        $batch_media = get_posts($query_args);
        $total_items = $total_items + count($batch_media);
        $paged++;

    } while (!empty($batch_media));

    $total_pages = ceil($total_items / $per_page) - 1;

    if ($page > $total_pages && $total_items > 0) {
        return new WP_Error('invalid_page', 'Invalid page, please check!', ['status' => 400]);
    }

    $query_args['posts_per_page'] = $per_page;
    $query_args['paged'] = $page + 1;
    $paginated_media = get_posts($query_args);

    $response = [
        'results' => array_map('get_media_response_data', $paginated_media),
        'pager' => [
            'count' => $total_items,
            'pages' => $total_pages + 1,
            'items_per_page' => $per_page,
            'current_page' => $page,
            'next_page' => $page < $total_pages ? $page + 1 : null,
        ],
    ];

    return new WP_REST_Response($response, 200);
}

function get_media_response_data($media)
{
    return [
        'mid' => $media->ID,
        'link' => wp_get_attachment_url($media->ID),
        'name' => $media->post_name,
        'alt_text' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
        'created' => $media->post_date_gmt,
    ];
}