<?php

/*
 * 
 */

class WCR_SSP
{
	private $options;
	protected $plugin_slug;
	
	function __construct()
	{
		$this->plugin_slug   = 'seriously-simple-podcasting';
				
		// Series の拡張
		add_filter( 'ssp_settings_fields' , array($this, 'ssp_setting_fields'), 10, 1);
		
		// Episode の拡張
		add_filter( 'ssp_episode_fields' , array($this, 'ssp_episode_fields'), 10, 1);
		
		// テンプレートの差し替え
		add_filter( 'ssp_feed_template_file' , array($this, 'ssp_feed_template_file'), 1, 1);
		
		// ショートコード
		add_shortcode('wcr_ssp', array($this, 'add_wcr_ssp_shortcode'));

		// ショートコードを series column に追加
		add_filter( 'manage_edit-series_columns', array( $this, 'edit_series_columns' ), 10 );
		add_filter( 'manage_series_custom_column', array( $this, 'add_series_columns' ), 2, 3 );
		
		// Episode に pub date を 追加し、並び替えを可能にする
		add_filter( 'manage_edit-podcast_columns', array( $this, 'edit_podcast_columns' ), 10 );
		add_filter( 'manage_podcast_posts_custom_column', array( $this, 'add_podcast_columns' ), 1, 3 );
		
		add_filter( 'request', array( $this , 'podcast_column_orderby_post_date' ) );
		add_filter( 'manage_edit-podcast_sortable_columns', array( $this, 'podcasts_register_sortable' ) );


		//管理画面設定
		if( is_admin() ){
	        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'page_init' ) );			
		}
		
		//表示数を変更
		add_action( 'pre_get_posts', array( $this, 'change_episode_per_page' ) );
		
		//RSS の表示数を増やす
		add_filter('ssp_feed_number_of_posts', function($num){ return 300; } );
		
		$this->options = get_option( 'wcr_ssp_options' );
	}
	
	
	// Series に、アクセス制限のための項目と、Podcastの形式（オーディオ、デフォルト）を追加する
	function ssp_setting_fields( $settings ){
		
		$settings['feed-details']['fields'][] = array(
					'id'          => 'wc_restrict_ssp',
					'label'       => __( '購入者制限をする', 'seriously-simple-podcasting' ),
					'description' => __( '購入者制限を行う場合は、ハイを選んでください', 'seriously-simple-podcasting' ),
					'type'        => 'radio',
					'options'     => array( 'restrict_enable' => __( 'Yes, Restrict', 'seriously-simple-podcasting' ), 'restrict_disable' => __( 'No, Restrict', 'seriously-simple-podcasting' ) ),
					'default'     => 'restrict_disable',
				);
		
		$settings['feed-details']['fields'][] = array(
					'id'          => 'wcr_ids',
					'label'       => __( 'WC Restrict IDs', 'seriously-simple-podcasting' ),
					'description' => __( 'Relative WC Restrict IDs (, is separation)', 'seriously-simple-podcasting' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( '1,2,3...', 'seriously-simple-podcasting' ),
					'callback'    => 'wp_strip_all_tags',
					'class'       => 'regular-text',
				);
		
		$settings['feed-details']['fields'][] = array(
					'id'          => 'product_ids',
					'label'       => __( 'WC Product IDs', 'seriously-simple-podcasting' ),
					'description' => __( 'Relative WC Product IDs (, is separation)', 'seriously-simple-podcasting' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( '1,2,3...', 'seriously-simple-podcasting' ),
					'callback'    => 'wp_strip_all_tags',
					'class'       => 'regular-text',
				);
				
		$settings['feed-details']['fields'][] = array(
					'id'          => 'sub_ids',
					'label'       => __( 'WC Subscription IDs', 'seriously-simple-podcasting' ),
					'description' => __( 'Relative WC Suscription IDs (, is separation)', 'seriously-simple-podcasting' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( '10,20,...', 'seriously-simple-podcasting' ),
					'callback'    => 'wp_strip_all_tags',
					'class'       => 'regular-text',
				);
				
		$settings['feed-details']['fields'][] = array(
					'id'          => 'mem_ids',
					'label'       => __( 'WC Membership IDs', 'seriously-simple-podcasting' ),
					'description' => __( 'Relative WC Membership IDs (, is separation)', 'seriously-simple-podcasting' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( '100,200,...', 'seriously-simple-podcasting' ),
					'callback'    => 'wp_strip_all_tags',
					'class'       => 'regular-text',
				);
		
/*
		$settings['feed-details']['fields'][] = array(
					'id'          => 'podcast_type',
					'label'       => __( 'Podcastタイプ', 'seriously-simple-podcasting' ),
					'description' => __( 'セミナー型、Podcast型かを選んでください', 'seriously-simple-podcasting' ),
					'type'        => 'radio',
					'options'     => array( 'ptype_seminar' => __( 'セミナー型', 'seriously-simple-podcasting' ), 'ptype_default' => __( 'Podcast型', 'seriously-simple-podcasting' ) ),
					'default'     => 'ptype_default',
				);
*/
		
		return $settings;	
	}
	
	// Episode に、アクセス制限のための項目を追加
	function ssp_episode_fields( $fields ){
		
		$fields['wcr_ssp_episode_restrict'] = array(
			'name'             => __( 'Restrict :', $this->plugin_slug ),
			'description'      => '',
			'type'             => 'radio',
			'default'          => 'disable',
			'options'          => array(
				'enable' => __( '制限する', $this->plugin_slug ),
				'disable' => __( '制限しない', $this->plugin_slug )
			),
			'section'          => 'info',
			'meta_description' => __( 'The setting of restriction', $this->plugin_slug ),
		);
		
		return $fields;
	}
	
	function ssp_feed_template_file( $template_file )
	{
		$template_file = dirname( dirname( __FILE__ ) ). '/templates/feed-podcast.php';
		return $template_file;
	}
	
	
	
	// -----------------------------------------------------------------------------
	//
	// ! Shortcode
	//
	// -----------------------------------------------------------------------------
	
	/* [wcr_ssp id="x" /] */
	function add_wcr_ssp_shortcode($atts) {
		$atts = shortcode_atts( array(
			'id'                => '',
			'label_podcast'     => 'iPhone、iPad、スマホ',
			'label_pcast'       => 'Mac、パソコン',
			'label_url'         => 'その他(URL)',
			'label_web'         => 'Web視聴する',
			'label_ok'          => '全編をご覧いただけます',
			'label_trial'       => '一部コンテンツをご覧いただけます',
			'label_ok_offer'    => '全編のお申し込みはこちら',
			'label_offer_trial' => '無料登録で、一部コンテンツをご覧いただけます',
			'template'          => '',
		), $atts );
		extract( $atts );
		
		if($id == ''){
			return '<p>invalid series id</p>';
		}
		
		// feed url の生成
		global $ss_podcasting;

		$series_id     = $id;				
		$series      = get_term( $series_id, 'series' );
		$series_slug = $series->slug;
				
		if ( get_option( 'permalink_structure' ) ) {
			$feed_slug = apply_filters( 'ssp_feed_slug', $ss_podcasting->token );
			$feed_url  = $ss_podcasting->home_url . 'feed/' . $feed_slug . '/' . $series_slug;
		} else {
			$feed_url = add_query_arg(
				array(
					'feed'           => $ss_podcasting->token,
					'podcast_series' => $series_slug,
				),
				$ss_podcasting->home_url
			);
		}
		
		//seckey から、feed url に付属させるパラメタを作成
		$user       = wp_get_current_user();
		$user_id    = $user->ID;
		$user_email = $user->user_email;
		
		if( $user_id != 0 ) {
			$user_logined = true;
			
			$seckey = ( isset( $this->options['seckey'] ) && $this->options['seckey'] != '' ) ? 
				$this->options['seckey'] : 
				WCR_SSP_SECKEY;
			$text      = 'wcr,' . $user->user_login . ',ssp,' . $user->ID;
			$enc_text  = toiee_xor_encrypt($text, $seckey);
			
			$add_param = '/?wcr_token='.$enc_text;
		}
		else {
			$user_logined = false;
			$add_param = '';
		}
		$wcr_feed_url = $feed_url . $add_param;
		
		
		
		// アクセスできる場合は、target="_blank" を入れる。そうでない場合は、uk-toggle="target: #modal" をセットする
		$target_toggle = 'target="_blank"';
		$message = '';
		
		$wc_restrict_ssp  = get_option( 'ss_podcasting_wc_restrict_ssp_' . $series_id, false );  // デフォルトは false
		if( $wc_restrict_ssp == 'restrict_enable' ) {  // もし、制限ありなら
			if( $user_logined ){  // ユーザーの制限をチェックして、メッセージを切り替える
				
				$product_url = '';
				$modal_html = '';
				
				$ret = $this->get_access_and_product_url( $user_email, $user_id, $series_id );

				
				// メッセージの生成
				if( $ret['access'] ) {
					$message = $label_ok;
				}
				else {
					$message = $label_trial;
					if( $ret['url'] != '' ){
						$message .= ' <a href="'.$ret['url'].'" class="uk-button uk-button-text">('.$label_ok_offer.')</a>';
					}
				}
								
			}
			else {  // ログインフォームを出す
				
				// error message がある場合、モーダルウィンドウを表示する（ための準備）
				ob_start();
				wc_print_notices();
				$wc_notices = ob_get_contents();
				ob_end_clean();
				
				$js = ( $wc_notices != '') ? 
					"<script>el = document.getElementById('modal_login_form');UIkit.modal(el).show();</script>"
					: '';

				// ログインフォームの取得
				ob_start();
				echo $wc_notices;
				woocommerce_login_form( array('redirect'=> get_permalink()) );
				echo $js;
				$login_form = ob_get_contents();
				ob_end_clean();

				// 登録フォームの取得
				ob_start();
?>				
		<h2><?php esc_html_e( 'Register', 'woocommerce' ); ?></h2>

		<form method="post" class="woocommerce-form woocommerce-form-register register">

			<?php do_action( 'woocommerce_register_form_start' ); ?>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" /><?php // @codingStandardsIgnoreLine ?>
				</p>

			<?php endif; ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" /><?php // @codingStandardsIgnoreLine ?>
			</p>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
					<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" />
				</p>

			<?php endif; ?>

			<?php do_action( 'woocommerce_register_form' ); ?>

			<p class="woocommerce-FormRow form-row">
				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="woocommerce-Button button" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
			</p>

			<?php do_action( 'woocommerce_register_form_end' ); ?>

		</form>
<?php
				$register_form = ob_get_contents();
				ob_end_clean();   //登録フォーム取得、ここまで


				$modal_html = <<<EOD
<!-- This is the modal -->
<div id="modal_login_form" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <h4>会員ログインが必要です</h4>
        <div class="uk-alert-success" uk-alert><p>無料登録することで、一部をご覧いただけます。<br>
        <a href="#toggle-form" uk-toggle="target: #toggle-form; animation: uk-animation-fade">無料登録はこちらをクリック</a>
        </p>
        </div>
        <div id="toggle-form" hidden class="uk-card uk-card-default uk-card-body uk-margin-small">
        {$register_form}
        </div>
        {$login_form}
        <p class="uk-text-right">
            <button class="uk-button uk-button-default uk-modal-close" type="button">閉じる</button>
        </p>
    </div>
</div>
EOD;

				$wcr_feed_url = '#';
				$target_toggle = 'uk-toggle="target: #modal_login_form"';
				$message = $label_offer_trial;
			}
		}
		

		$url_scheme_feed  = str_replace('https://', 'podcast://', $wcr_feed_url);
		$url_scheme_pcast = str_replace('https://', 'pcast://', $wcr_feed_url);
		
		$template = '
<p><a href="%FEED%" class="uk-button uk-button-secondary" %TARGET_TOGLE%>' .$label_podcast. '</a>
 <a href="%PCAST%" class="uk-button uk-button-secondary" %TARGET_TOGLE%>'  .$label_pcast.   '</a>
 <a href="%URL%" %TARGET_TOGLE% class="uk-button uk-button-text">'         .$label_url.     '</a><br>
 <span class="uk-text-meta uk-text-small">%MESSAGE%</span>
 </p>';
 
		return str_replace(
					array('%FEED%',         '%PCAST%',         '%URL%',       '%TARGET_TOGLE%' , '%MESSAGE%'),
					array($url_scheme_feed, $url_scheme_pcast, $wcr_feed_url, $target_toggle,    $message), 
					$template
				) . $modal_html;	
	}
	
	
	public function get_access_and_product_url( $user_email, $user_id, $series_id ) {
		
		$product_url = '';
		
		// 関連商品IDs の取得
		$wc_prods = array();
		foreach( array('product_ids', 'sub_ids', 'mem_ids') as $tmp_field ) {
			$dat = get_option( 'ss_podcasting_' . $tmp_field . '_' . $series_id, false );
			$ids = explode(',' , $dat);
			
			$wc_prods[ $tmp_field ] = $ids;
		}
				
		// WC Restrict の情報を取得し設定
		$wcr_id = get_option( 'ss_podcasting_wcr_ids_' . $series_id );
		if( $wcr_id != '' && is_numeric($wcr_id) ){
			$wcr_dat  = get_post_meta($wcr_id, 'wcr_param', true);
			$wcr_arr = unserialize( $wcr_dat );
			
			$tmp_arr = array( 'product', 'sub','mem' );
			foreach($tmp_arr as $name) {				
				$wc_prods[ $name.'_ids' ] = array_merge( $wc_prods[ $name.'_ids' ], $wcr_arr['wcr_'.$name.'_ids'] );
			}
		}
									
		// 通常商品のチェック
		foreach($wc_prods['product_ids'] as $i)
		{
			$access = wc_customer_bought_product( $user_email, $user_id, $i );
			if( $product_url == '') {  // 商品ページを探す
				$product = wc_get_product( $i );
				$product_url = is_object( $product ) ? get_permalink( $product->get_id() ) : '';
			}
			
			if($access){
				return array(
					'access' => true,
					'url'    => $product_url
				);
			}
		}
		
		// subscription のチェック
		if ( function_exists('wcs_user_has_subscription') )
		{
			foreach( $wc_prods['sub_ids'] as $i )
			{
				if( $product_url == '') {  // 商品ページを探す
					$product = wc_get_product( $i );
					$product_url = is_object( $product ) ? get_permalink( $product->get_id() ) : '';
				}
				
				$access = ($i != '') ? wcs_user_has_subscription( $user_id, $i, 'active') : false;
				if( $access ){
					return array(
						'access' => true,
						'url'    => $product_url
					);
				}
			}
		}
		
		// Membership でチェックする
		if ( function_exists( 'wc_memberships' )  ) {
			foreach( $wc_prods['mem_ids'] as $i )
			{
				$access = ($i != '') ? wc_memberships_is_user_active_member(  $user_id, $i ) : false;
				if( $access ){
					return array(
						'access' => true,
						'url'    => $product_url
					);
				}
			}
		}
		
		return array( 'access' => false, 'url' => $product_url );
	}
	
	
	public function edit_series_columns( $columns ) {
		$columns['shortcode'] = __( 'Shortcode', 'wcr-ssp' );
		return $columns;
	}

	public function add_series_columns( $column_data, $column_name, $term_id ) {
		switch ( $column_name ) {
			case 'shortcode':
				$column_data = '[wcr_ssp id="'.$term_id.'" /]';
				break;
		}
		return $column_data;
	}

	public function edit_podcast_columns( $columns ) {		
		$columns[ 'post_date' ] = __( '公開日', 'wcr-ssp');
		return $columns;
	}
	
	public function add_podcast_columns( $column, $post_id ) {
		switch ( $column )	{
			case 'post_date':
				echo get_the_date( "Y/n/j G:i", $post_id );
				break;
		}
	}
	
	public function podcast_column_orderby_post_date( $vars ) {
		if ( isset( $vars['orderby'] ) && $vars['orderby'] == 'post_data' ) {
	        $vars = array_merge( $vars, array(
	            'meta_key' => 'post_data',
	            'orderby' => 'meta_value'
	        ));
	    }
	    return $vars;
	}
	
	public function podcasts_register_sortable( $sortable_column ) {
		$sortable_column['post_date'] = 'post_date';
		return $sortable_column;
	}
	
	// -----------------------------------------------------------------------------
	//
	// ! Admin settings
	//
	// -----------------------------------------------------------------------------
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'WC Restrict SSP設定', 
            'WC Restrict SSP', 
            'manage_options', 
            'wcr-ssp-admin', 
            array( $this, 'create_admin_page' )
        );
    }
    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'wcr_ssp_options' );
        ?>
        <div class="wrap">

            <h2>WooCommerce Restrict Seriously Simple Podcast設定</h2>           
            <p>暗号用のキーを設定します</p>
	           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'wcr_ssp_group' );   
                do_settings_sections( 'wcr-ssp-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }
    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'wcr_ssp_group', // Option group
            'wcr_ssp_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
        add_settings_section(
            'setting_section_id', // ID
            '暗号キー', // Title
            array( $this, 'print_section_info' ), // Callback
            'wcr-ssp-setting-admin' // Page
        );  
        add_settings_field(
            'seckey', // ID
            '暗号キー', // Title 
            array( $this, 'seckey_callback' ), // Callback
            'wcr-ssp-setting-admin', // Page
            'setting_section_id' // Section           
        );
    }
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
	    return $input;
	    // サニタイズしない
        $new_input = array();

        if( isset( $input['seckey'] ) )
            $new_input['seckey'] = wp_kses_post( $input['seckey'] );

        return $new_input;
    }
    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print '以下に設定を指定し、変更を保存をクリックしてください。';
    }
    /** 
     * Get the settings option array and print one of its values
     */
    public function seckey_callback()
    {
   	    $text = isset( $this->options['seckey'] ) ? $this->options['seckey'] : '';
?>

<input type="text" name="wcr_ssp_options[seckey]" value="<?php echo $text; ?>">


<?php
	}
	
	/** episode の一覧数（seriesタクソノミーのアーカイブ表示の場合）を制御
		
		表示数を増やす。
		
		
	*/
	public function change_episode_per_page( $query ) {
	    if ( is_admin() || ! $query->is_main_query() )
	    {
	        return;
	    }
	    
	    if ( $query->is_tax( 'series' ) ) {
	        $query->set( 'posts_per_page', '300' ); //表示件数を指定
	        
	        $term       = get_term_by( 'slug', $query->get('series'), 'series');
	        $series_id  = $term->term_id;
	        $pcast_type = get_option( 'ss_podcasting_podcast_type_'.$series_id, 'ptype_default' ); 
	        
	        if( $pcast_type == 'ptype_seminar' ){ // 順序の変更
		        $query->set( 'orderby', 'post_date' );
		        $query->set( 'order', 'ASC' );
	        }
	        else{
		        $query->set( 'orderby', 'post_date' );
		        $query->set( 'order', 'DESC' );
	        }
	    }
	}
	
	
}

