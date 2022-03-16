<?php
/**
 * Plugin Name: WebePartners
 * Plugin URI: https://wordpress.org/plugins/webepartners/
 * Description: WebePartners integration.
 * Version: 0.0.1
 * Author: Paweł Worzała
 *
 * Text Domain: webepartners
 *
 * WC requires at least: 5.7
 * WC tested up to: 5.7
 */

ini_set('max_execution_time',3600*3);

function webepartners_install_plugin( $api, $loop = false ) {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $table_name = $wpdb->prefix . 'webepartners_programs';
    $sql = "CREATE TABLE $table_name (
        program_id INTEGER NOT NULL AUTO_INCREMENT,
        program_name TEXT NOT NULL,
        program_url TEXT NOT NULL,
        active bool NOT NULL default true,
        PRIMARY KEY (program_id)
    ) $charset_collate;";
    dbDelta($sql);
}
register_activation_hook( __FILE__, 'webepartners_install_plugin' );



function webepartners_plugin_setup_menu(){
    add_menu_page( 'WebePartners', 'WebePartners', 'manage_options', 'webepartners', 'webepartners_plugin_page_init' );
}
function webepartners_plugin_page_init(){
    if(isset($_POST['name'])){
        global $wpdb;
        $table_name = $wpdb->prefix . 'webepartners_programs';
        $wpdb->insert($table_name, array(
                'program_name' => $_POST['name'],
                'program_url'  =>   $_POST['url'],
            )
        );
    }
    if(isset($_POST['program_id'])){
        import_data_from_xml((int)$_POST['program_id']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'webepartners_programs';
    $results = $wpdb->get_results( "SELECT program_id,program_name FROM ".$table_name."");
    $imports = '';
    foreach($results as $result){
        $imports .= '<form method="post" enctype="multipart/form-data">
            <input type="hidden" name="program_id" value="'.$result->program_id.'"/>
            <button type="submit">Importuj '.$result->program_name.'</button>
        </form>';
    }

    echo '<h1>Hello World!</h1>
        '.$imports.'
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="name"/>
            <input type="text" name="url"/>
            <button type="submit">Wyślij</button>
        </form>';
}
add_action('admin_menu', 'webepartners_plugin_setup_menu');



function webepartners_tax_rewrite() {
    $labels = array(
        'name'                       => _x( 'Categories', 'taxonomy general name', 'textdomain' ),
        'singular_name'              => _x( 'Category', 'taxonomy singular name', 'textdomain' ),
        'search_items'               => __( 'Search Categories', 'textdomain' ),
        'popular_items'              => __( 'Popular Categories', 'textdomain' ),
        'all_items'                  => __( 'All Categories', 'textdomain' ),
        'parent_item'                => null,
        'parent_item_colon'          => null,
        'edit_item'                  => __( 'Edit Category', 'textdomain' ),
        'update_item'                => __( 'Update Category', 'textdomain' ),
        'add_new_item'               => __( 'Add New Category', 'textdomain' ),
        'new_item_name'              => __( 'New Category Name', 'textdomain' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'textdomain' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'textdomain' ),
        'choose_from_most_used'      => __( 'Choose from the most used categories', 'textdomain' ),
        'not_found'                  => __( 'No categories found.', 'textdomain' ),
        'menu_name'                  => __( 'Categories', 'textdomain' ),
    );
 
    $args = array(
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'category' ),
    );
 
    register_taxonomy( 'category', 'offer', $args );
}
add_action( 'init', 'webepartners_tax_rewrite' );


function import_data_from_xml($program_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'webepartners_programs';
    $results = $wpdb->get_results( "SELECT program_url FROM ".$table_name." where program_id=".$program_id);

    $data = file_get_contents($results[0]->program_url);
    $xml = simplexml_load_string($data);

    foreach($xml->offers[0]->offer as $offer){     
        $my_post = array(
            'post_title'    => wp_strip_all_tags( (string)$offer->name ),
            'post_content'  => (string)$offer->description,
            'post_status'   => 'publish',
            'post_author'   => 1,//$user_ID,
            'post_parent'   => 0,
            'menu_order'    => 0,
            'guid'          => '',
            'post_excerpt'  => '',
            'post_type'  => 'offer',
            //'tax_input'     => array( 'category' => ['term', 'term2', 'term3'] ),
            'meta_input'    => array( 
                'program_id'=>$program_id,
                'outer_id'=>(string)$offer->id,
                'url'=>(string)$offer->url,
                'image'=>(string)$offer->image,
                'price'=>(float)str_replace(',','.',$offer->price),
                'category'=>(string)$offer->category,
                'shopcategory'=>(string)$offer->shopcategory,
                'producer'=>(string)$offer->producer,
                //'product_url'=>(string)$offer->attributes()->ProductUrl,
                //'ean'=>(string)$offer->attributes()->EAN,
            ),
        );


        $categories = explode('>',$my_post['meta_input']['category']);
        $lastId = null;
        $categoriesIds = [];
        foreach($categories as $category){
            $terms = get_terms(array(
                'taxonomy'=>'category',
                'hide_empty' => false,
                'slug'=> $category,
            ));
            
            if(count($terms)===0){
                $term = wp_insert_term(
                    $category,
                    'category',
                    array(
                        'slug'        => $category,
                        'parent'      => $lastId,
                    )
                );
                $lastId = $term['term_taxonomy_id'];
            }else{
                $lastId = $terms[0]->term_taxonomy_id;
            }
            $categoriesIds[] = $lastId;
        }


        $args = array(
            'post_type'=>'offer',
            'posts_per_page' => 1,
             'meta_query' => array(
                array(
                    'key'=>'outer_id',
                    'field'=>'slug',
                    'value'=>(string)$offer->id,
                    'compare' => '=',
                    'type' => 'numeric',
                ),
            )
        );
        $query = new WP_Query( $args );

        $my_post['tax_input'] = array( 'category' => $categoriesIds );

        if($query->have_posts()){
            $query->the_post();
            $my_post['ID'] = get_the_ID();
            wp_update_post( wp_slash($my_post) );
        }else{
            wp_insert_post( wp_slash($my_post) );
        }
    }
}








$post_type = 'offer';
global $post_type;

function webepartnerts_post_type_init() {
    global $post_type;
    //$post_type = 'offer';
    $labels = array(
        'name'                  => _x( 'Offers', 'Offers type general name', 'textdomain' ),
        'singular_name'         => _x( 'Offer', 'Offer type singular name', 'textdomain' ),
        'menu_name'             => _x( 'Offers', 'Admin Menu text', 'textdomain' ),
        'name_admin_bar'        => _x( 'Offer', 'Add New on Toolbar', 'textdomain' ),
        /*'add_new'               => __( 'Add New', 'textdomain' ),
        'add_new_item'          => __( 'Add New Book', 'textdomain' ),
        'new_item'              => __( 'New Book', 'textdomain' ),
        'edit_item'             => __( 'Edit Book', 'textdomain' ),
        'view_item'             => __( 'View Book', 'textdomain' ),
        'all_items'             => __( 'All Books', 'textdomain' ),
        'search_items'          => __( 'Search Books', 'textdomain' ),
        'parent_item_colon'     => __( 'Parent Books:', 'textdomain' ),
        'not_found'             => __( 'No books found.', 'textdomain' ),
        'not_found_in_trash'    => __( 'No books found in Trash.', 'textdomain' ),
        'featured_image'        => _x( 'Book Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'textdomain' ),
        'archives'              => _x( 'Book archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'textdomain' ),
        'insert_into_item'      => _x( 'Insert into book', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'textdomain' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this book', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'textdomain' ),
        'filter_items_list'     => _x( 'Filter books list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'textdomain' ),
        'items_list_navigation' => _x( 'Books list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'textdomain' ),
        'items_list'            => _x( 'Books list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'textdomain' ),
    */);
 
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => $post_type ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
    );
 
    register_post_type( $post_type, $args );
 
    register_meta_customs_fields($post_type);
}
add_action( 'init', 'webepartnerts_post_type_init' );


$custom_meta_fields = [];
global $custom_meta_fields;

function add_custom_field($name){
    global $custom_meta_fields;
    $custom_meta_fields[$name] = array(
        'label'=> 'Author Name',
        'desc'  => 'Enter post author name to be displayed',
        'id'    => $name,
        'type'  => 'text'
    );
}
//add_custom_field('outer_id');
add_custom_field('image');
//add_custom_field('description');

function register_meta_customs_fields($post_type) {
    register_meta_custom($post_type,'program_id','integer');
    register_meta_custom($post_type,'outer_id','string');
    //register_meta_custom($post_type,'name','string');
    //register_meta_custom($post_type,'description','string');
    register_meta_custom($post_type,'url','string');
    register_meta_custom($post_type,'image','string');
    register_meta_custom($post_type,'price','float');
    register_meta_custom($post_type,'category','string');
    register_meta_custom($post_type,'shopcategory','string');
    register_meta_custom($post_type,'producer','string');
    //register_meta_custom($post_type,'product_url','string');
    //register_meta_custom($post_type,'ean','string');
    register_meta_custom($post_type,'visits','integer');
}

function register_meta_custom($post_type,$field,$type) {
    $args2 = array(
        'type' => $type,
        'description' => 'A meta key associated with a string meta value.',
        'single' => false,
        'show_in_rest' => true,
    );
    register_meta($post_type, $field, $args2);
}

function add_custom_meta_box() {
    global $custom_meta_fields;
    global $post_type;
    foreach ($custom_meta_fields as $key=>$field) {
        add_meta_box(
            $key,
            'Custom Meta Box',
            'show_custom_meta_box',
            $post_type,
            'normal',
            'high'
        ); 
    }
}
add_action('add_meta_boxes', 'add_custom_meta_box');

function show_custom_meta_box() {
    global $custom_meta_fields, $post;
 
    foreach ($custom_meta_fields as $field) {
        $meta = get_post_meta($post->ID, $field['id'], true);
        echo '<label for="'.$field['id'].'">'.$field['label'].'</label>';
        switch($field['type']) {
            case 'text':
                echo '<input type="text" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'"/>
                    <br/>';
            break;
        }
    }
}


function save_custom_meta($post_id) {
    global $custom_meta_fields;
     
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return $post_id;
        
    foreach ($custom_meta_fields as $field) {
        $old = get_post_meta($post_id, $field['id'], true);
        if(!isset($_POST[$field['id']])){
            continue;
        }
        $new = $_POST[$field['id']];
        if ($new && $new != $old) {
            update_post_meta($post_id, $field['id'], $new);
        } elseif ('' == $new && $old) {
            delete_post_meta($post_id, $field['id'], $old);
        }
    }
}
add_action('save_post', 'save_custom_meta');























if ( ! function_exists( 'webepartners_posts_function' ) ) {
	function webepartners_posts_function($atts) {
		$atts = array_change_key_case( (array) $atts, CASE_LOWER );

		$count = 12;

		$category = null;
		if(isset($_GET['catalog'])){
			$category = $_GET['catalog'];
		}
        $producer = null;
		if(isset($_GET['producer'])){
			$producer = $_GET['producer'];
		}
        $price_sort = null;
		if(isset($_GET['price_sort'])){
			$price_sort = $_GET['price_sort'];
		}
        $price_from = null;
		if(isset($_GET['price_from'])){
			$price_from = (float)$_GET['price_from'];
		}
        $price_from = $price_from?$price_from:null;
        $price_to = null;
		if(isset($_GET['price_to'])){
			$price_to = (float)$_GET['price_to'];
		}
        $price_to = $price_to?$price_to:null;
        $bestsellers = null;
		if(isset($_GET['bestsellers'])){
			$bestsellers = $_GET['bestsellers']==='true';
		}

		$paged = (get_query_var('page')) ? get_query_var('page') : 1;

        $tax_query = [];
        if($category){
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => $category,
            );
        }

        $meta_query = [];
        if($producer){
            $meta_query[] = array(
                'key'      => 'producer',
                'compare'  => '=',
                'value'    => $producer, 
            );
        }
        if($price_from){
            $meta_query[] = array(
                'key'      => 'price',
                'compare'  => '>=',
                'value'    => $price_from, 
                'type' => 'NUMERIC'
            );
        }
        if($price_to){
            $meta_query[] = array(
                'key'      => 'price',
                'compare'  => '<=',
                'value'    => $price_to, 
                'type' => 'NUMERIC'
            );
        }

		$query = array(
			'post_type'                 => 'offer',
			'post_status'               => 'publish',
			'posts_per_page'            => $count,

            'meta_key'			=> $bestsellers?'visits':'price',
            'meta_type'			=> 'NUMERIC',
            'orderby'			=> 'meta_value',
            'order'				=> $bestsellers?'desc':($price_sort?$price_sort:'asc'),

			'paged' => $paged,
		);
        if(count($tax_query)){
            $query['tax_query'] = $tax_query;
            $query['tax_query']['relation'] = 'AND';
        }
        if(count($meta_query)){
            $query['meta_query'] = $meta_query;
            $query['meta_query']['relation'] = 'AND';
        }

		$custom_query = new WP_Query($query);

        $buffor = '';

        $buffor .= '<div class="filters">';

        $buffor .= '<div class="left">';
        $genre_url = add_query_arg('', '', $_SERVER['REQUEST_URI']);
        $buffor .= '<form action="'.$genre_url.'">';
        $buffor .= '<input type="text" name="price_from" value="'.(($price_from!==0)?$price_from:'').'" placeholder="Cena od" class="price"/>';
        $buffor .= '<input type="text" name="price_to" value="'.(($price_to!==0)?$price_to:'').'" placeholder="Cena do" class="price"/>';
        $buffor .= '<button type="button" onclick="send()">Filtruj</button>';
        $buffor .= '<script>
            function send(){
                const urlParams = new URLSearchParams(window.location.search);
                const inputs = document.querySelectorAll("form input")
                inputs.forEach(input=>{
                    urlParams.set(input.name,input.value)
                })
                let url = urlParams.toString()
                url = url.replace("&s=&=Szukaj","")
                window.location = "?"+url
            }
        </script>';
        $buffor .= '</form>';
        $buffor .= '</div>';

        $buffor .= '<div class="right">';
        $buffor .= '<script>
            function setFilter(name,value){
                const urlParams = new URLSearchParams(window.location.search);
                if(urlParams.get(name)){
                    urlParams.delete(name)
                }else{
                    urlParams.set(name,value)
                }
                let url = urlParams.toString()
                url = url.replace("&s=&=Szukaj","")
                window.location = "?"+url
            }
        </script>';
        $buffor .= '<a class="'.(($price_sort=='asc')?'active':'').'" onclick="setFilter(\'price_sort\',\'asc\')">Sortuj rosnąco</a>';
        $buffor .= '<a class="'.(($price_sort=='desc')?'active':'').'" onclick="setFilter(\'price_sort\',\'desc\')">Sortuj malejąco</a>';
        $buffor .= '<a class="'.($bestsellers?'active':'').'" onclick="setFilter(\'bestsellers\',\'true\')">Bestsellers</a>';
        $buffor .= '</div>';

        $buffor .= '</div>';

		$buffor .= '<div class="offers">';
        if($custom_query->have_posts()){
            while ($custom_query->have_posts()){
                $custom_query->the_post();

                $buffor .= '<div class="offer"><a target="_blank" href="wp-json/redirect/v1/redirect/'.get_field('program_id').'/'.get_field('outer_id').'">';
                $buffor .= '<div class="img"><img src="'.get_field('image').'"/></div>';
                $buffor .= '<div class="title">';
                $buffor .= substr(get_the_title(),0,60);
                $buffor .= '</div>';
                
                $buffor .= '<div class="description">';
                $buffor .= substr(get_the_excerpt(),0,200);
                $buffor .= '</div>';
 
                $buffor .= '<div class="producer">'.get_field('producer').'</div>';
                $buffor .= '<div class="price">'.number_format((float)get_field('price'),2,',','.').' PLN</div>';
                
                $buffor .= '</a></div>';
            }
        }else{
            $buffor .= '<h3>brak wyników do wyświetlenia</h3>';
        }
		
		$buffor .= '</div>';

        $buffor .= '<div id="pagination">';
        $buffor .= paginate_links( array(
            'base' => str_replace( 999999, '%#%', esc_url( get_pagenum_link( 999999 ) ) ),
            'format' => '?paged=%#%',
            'current' => $paged,
            'total' => $custom_query->max_num_pages
        ));
        $buffor .= '</div>';

		wp_reset_query();

        return $buffor;
	}
}
add_shortcode( 'webepartners_posts', 'webepartners_posts_function' );


function webepartners_categories_function(){
    $category = null;
    if(isset($_GET['catalog'])){
        $category = $_GET['catalog'];
    }

    $buffor = '';

    $buffor .= '<div class="breadcumbs">';
    $termsList = get_terms(array(
        'taxonomy'=>'category',
        'slug'=>$category,
    ));
    $term = get_term($termsList[0]->term_id);
    $parent = $term->parent;
    $tmp = '';
    $prev = null;
    while($parent){
        $term= get_term($parent);

        $genre_url = add_query_arg('catalog', $term->slug, $_SERVER['REQUEST_URI']);

        $tmp = '<a href="'.$genre_url.'">'.$term->name.'</a>'.($prev?'>':'').$tmp;

        $prev = $parent;
        $parent = $term->parent;

        if($prev === $parent){
            break;
        }
    }
    $buffor .= $tmp.'</div>';

    $terms = $category?get_terms(array(
        'taxonomy'=>'category',
        'hide_empty' => false,
        'slug'=>$category,
    )):null;

    $terms = get_terms(array(
        'taxonomy'=>'category',
        'hide_empty' => false,
        'parent'=>isset($terms)?$terms[0]->term_id:null,
    ));

    $buffor .= $category?('<h1>'.$category.'</h1>'):'';

    $buffor .= '<div class="catalogs">';

    foreach($terms as $term){
        $genre_url = add_query_arg('catalog', $term->slug, $_SERVER['REQUEST_URI']);

        $buffor .= '<div class="catalog '.($category===$term->name?'active':'').'"><a href="'.$genre_url.'">'.$term->name.'</a></div>';
    }

    $buffor .= '</div>';

    return $buffor;
}
add_shortcode( 'webepartners_categories', 'webepartners_categories_function' );


function webepartners__registration_url_action() {
    register_rest_route( 'redirect/v1', '/redirect/(?P<program_id>[0-9]+)/(?P<outer_id>[a-zA-Z0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'webepartners__registration_url_action_callback',
    ));
}
function webepartners__registration_url_action_callback($request) {
    $query = array(
        'post_type'                 => 'offer',
        'post_status'               => 'publish',
        'posts_per_page'            => -1,
    );
    $query['meta_query']=[];
    $query['meta_query'][] = array(
        'key'      => 'program_id',
        'compare'  => '=',
        'value'    => $request->get_url_params()['program_id'], 
    );
    $query['meta_query'][] = array(
        'key'      => 'outer_id',
        'compare'  => '=',
        'value'    => $request->get_url_params()['outer_id'], 
    );
    $query['meta_query']['relation'] = 'AND';
    
    $custom_query = new WP_Query($query);
    $custom_query->the_post();

    $my_post = array(
        'ID'=>get_the_ID(),
        'meta_input'    => array( 
            'visits'=>((int)get_field('visits'))+1,
        ),
    );
    wp_update_post( wp_slash($my_post) );

    header('Location: '.get_field('url'));
    exit;
}
add_action('rest_api_init', 'webepartners__registration_url_action');




if ( ! function_exists( 'webepartners_producers_function' ) ) {
	function webepartners_producers_function($atts) {
		$atts = array_change_key_case( (array) $atts, CASE_LOWER );

		$count = -1;

        $category = null;
        if(isset($_GET['catalog'])){
            $category = $_GET['catalog'];
        }

        $producer = null;
        if(isset($_GET['producer'])){
            $producer = $_GET['producer'];
        }

        $terms = $category?get_terms(array(
            'taxonomy'=>'category',
            'hide_empty' => false,
            'slug'=>$category,
        )):null;

        global $wpdb;

        $states = $terms?$wpdb->get_results("SELECT distinct wp_postmeta.meta_value FROM wp_posts, wp_postmeta, wp_term_relationships, wp_terms 
        WHERE term_id = '".($terms[0]->term_id)."' AND term_taxonomy_id = '".($terms[0]->term_taxonomy_id)."' AND ID = post_id 
        AND ID = object_id AND post_type = 'offer' AND post_status = 'publish' 
        AND meta_key = 'producer' ORDER BY meta_value 
        limit 40"):
                $wpdb->get_results("SELECT DISTINCT meta_value 
                FROM wp_postmeta 
                WHERE meta_key = 'producer'
                ORDER BY meta_value limit 40");

        $buffor = '';

		$buffor .= '<div class="producers">';
		foreach ($states as $state) {
            $genre_url = add_query_arg('producer', $state->meta_value, $_SERVER['REQUEST_URI']);

			$buffor .= '<div class="producer '.($producer===$state->meta_value?'active':'').'">';
		
			$buffor .= '<div class="name">';
			$buffor .= '<a href="'.$genre_url.'">'.$state->meta_value.'</a>';
			$buffor .= '</div>';
		
            $buffor .= '</div>';
		}
		
		$buffor .= '</div>';

		wp_reset_query();

        return $buffor;
	}
}
add_shortcode( 'webepartners_producers', 'webepartners_producers_function' );


add_action('init','webepartners_style');
function webepartners_style()
{
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'style1', $plugin_url . 'style.css' );
}

function webepartners_add_rewrite_rule( $vars ){
    add_rewrite_rule('catalog/([0-9]+)', '?catalog=$1', 'top');
}
add_filter( 'init', 'webepartners_add_rewrite_rule', 10, 1 );