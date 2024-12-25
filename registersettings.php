add_action('admin_init', array($this, 'register_settings'));


public function register_settings() {
        // Register General API Settings
        register_setting(
            'ms_bunnystream_settings',
            'ms_bunnystream_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'ms_bunnystream_settings',
            'ms_bunnystream_library_id',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Register Teacher Quota Settings
        register_setting(
            'ms_bunnystream_settings',
            'ms_bunnystream_default_storage_limit',
            array(
                'type' => 'number',
                'sanitize_callback' => 'absint',
                'default' => 10 // Default 10GB
            )
        );

        register_setting(
            'ms_bunnystream_settings',
            'ms_bunnystream_default_bandwidth_limit',
            array(
                'type' => 'number',
                'sanitize_callback' => 'absint',
                'default' => 100 // Default 100GB
            )
        );

        // Add Settings Sections
        add_settings_section(
            'ms_bunnystream_api_section',
            'API Configuration',
            array($this, 'render_api_section'),
            'ms-bunnystream-settings'
        );

        add_settings_section(
            'ms_bunnystream_quota_section',
            'Default Teacher Quotas',
            array($this, 'render_quota_section'),
            'ms-bunnystream-settings'
        );

        // Add Settings Fields
        add_settings_field(
            'ms_bunnystream_api_key',
            'API Key',
            array($this, 'render_api_key_field'),
            'ms-bunnystream-settings',
            'ms_bunnystream_api_section'
        );

        add_settings_field(
            'ms_bunnystream_library_id',
            'Default Library ID',
            array($this, 'render_library_id_field'),
            'ms-bunnystream-settings',
            'ms_bunnystream_api_section'
        );

        add_settings_field(
            'ms_bunnystream_default_storage_limit',
            'Default Storage Limit (GB)',
            array($this, 'render_storage_limit_field'),
            'ms-bunnystream-settings',
            'ms_bunnystream_quota_section'
        );

        add_settings_field(
            'ms_bunnystream_default_bandwidth_limit',
            'Default Bandwidth Limit (GB/year)',
            array($this, 'render_bandwidth_limit_field'),
            'ms-bunnystream-settings',
            'ms_bunnystream_quota_section'
        );
    }

    public function render_api_section() {
        echo '<p>Enter your Bunny Stream API credentials below.</p>';
    }

    public function render_quota_section() {
        echo '<p>Set default storage and bandwidth limits for new teachers.</p>';
    }

    public function render_api_key_field() {
        $api_key = get_option('ms_bunnystream_api_key');
        ?>
        <input type="password" 
               id="ms_bunnystream_api_key" 
               name="ms_bunnystream_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text">
        <?php
    }

    public function render_library_id_field() {
        $library_id = get_option('ms_bunnystream_library_id');
        ?>
        <input type="text" 
               id="ms_bunnystream_library_id" 
               name="ms_bunnystream_library_id" 
               value="<?php echo esc_attr($library_id); ?>" 
               class="regular-text">
        <?php
    }

    public function render_storage_limit_field() {
        $storage_limit = get_option('ms_bunnystream_default_storage_limit', 10);
        ?>
        <input type="number" 
               id="ms_bunnystream_default_storage_limit" 
               name="ms_bunnystream_default_storage_limit" 
               value="<?php echo esc_attr($storage_limit); ?>" 
               class="small-text">
        <span class="description">GB</span>
        <?php
    }

    public function render_bandwidth_limit_field() {
        $bandwidth_limit = get_option('ms_bunnystream_default_bandwidth_limit', 100);
        ?>
        <input type="number" 
               id="ms_bunnystream_default_bandwidth_limit" 
               name="ms_bunnystream_default_bandwidth_limit" 
               value="<?php echo esc_attr($bandwidth_limit); ?>" 
               class="small-text">
        <span class="description">GB per year</span>
        <?php
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Bunny Stream Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ms_bunnystream_settings');
                do_settings_sections('ms-bunnystream-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    