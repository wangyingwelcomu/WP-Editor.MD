<?php
class editormd {

    private static $instance;

    //停用模式
    public function deactivate() {
        global $current_user;
        update_user_option($current_user->ID, 'rich_editing', 'true', true);
        delete_option('editormd_emoji_support');
    }

    //启用模式
    public function activate() {
        global $current_user;
        update_user_option($current_user->ID, 'rich_editing', 'false', true);
        add_option('editormd_emoji_support', '');
    }

    public static function getInstance() {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    // 提取jetpack模块
    function jetpack_markdown_posting_always_on() {
        global $wp_settings_fields;
        if (isset($wp_settings_fields['writing']['default'][WPCom_Markdown::POST_OPTION])) {
            unset($wp_settings_fields['writing']['default'][WPCom_Markdown::POST_OPTION]);
        }
    }

    // 提取jetpack模块-->载入语言
    function jetpack_markdown_load_textdomain() {
        load_plugin_textdomain('jetpack', false, dirname(plugin_basename(__FILE__)) . '/jetpack/languages/');
    }

    // 载入插件设置
    function jetpack_markdown_settings_link($actions) {
        return array_merge(
            array('settings' => sprintf('<a href="%s">%s</a>', 'options-general.php?page=editormd-settings', __('Settings', 'jetpack'))),
            $actions
        );
    }

    //加载编辑器相关配置
    public function load_editormd() {
        if (get_current_screen()->base !== 'post') {
            return;
        }
        ?>
        <script type="text/javascript">
            //<![CDATA[
            // 初始化編輯器
            var EditorMD;
            $(function() {
                EditorMD = editormd("wp-content-editor-container", {
                    width            : "100%", //编辑器宽度
                    height           : 640,    //编辑器高度
                    syncScrolling    : true,   //即是否开启同步滚动预览
                    htmlDecode       : true,   //开启HTML解析
                    toolbarAutoFixed : true,   //工具栏是否自动固定
                    path             : "<?php echo CAT_URL?>/lib/", //资源路径
                    emoji            : <?php echo get_option('editormd_emoji_support') ? 'true' : 'false' ?> ,
                    toolbarIcons     : function() {
                        // Or return editormd.toolbarModes[name]; // full, simple, mini
                        // Using "||" set icons align right.
                        return [
                            "undo", "redo", "|",
                            "bold", "del", "italic", "quote", "ucwords", "uppercase", "lowercase", "|",
                            "h1", "h2", "h3", "h4", "h5", "h6", "|",
                            "list-ul", "list-ol", "hr", "|",
                            "link", "reference-link", "image", "code", "preformatted-text", "code-block", "table", "datetime", <?php if ( get_option('editormd_emoji_support') == 'yes' ) {echo '"emoji",';} ?> "html-entities", "more", "pagebreak", "|",
                            "goto-line", "watch", "preview", "fullscreen", "clear", "search", "|",
                            "help", "info"
                        ];
                    }, //自定义标题栏
                    toolbarIconsClass: {
                        more: "fa-arrows-h" //指定一个FontAawsome的图标类
                    },
                    // 自定义工具栏按钮的事件处理
                    toolbarHandlers  : {
                        /**
                         * @param {Object}      cm         CodeMirror对象
                         * @param {Object}      icon       图标按钮jQuery元素对象
                         * @param {Object}      cursor     CodeMirror的光标对象，可获取光标所在行和位置
                         * @param {String}      selection  编辑器选中的文本
                         */
                        more: function (cm, icon, cursor, selection) {
                            cm.replaceSelection("<!--more-->");
                        }},
                    lang           : {
                        toolbar: {
                            more: "摘要分隔符"
                        }
                    },
                    onfullscreen : function() {
                        window.document.getElementById("wp-content-editor-container").style.position="fixed";
                    },//强制全屏
                    onfullscreenExit : function() {
                        window.document.getElementById("wp-content-editor-container").style.position="relative";
                    }//退出全屏返回原来的样式
                });
            });
            //替换后台编辑器Emoji源
            editormd.emoji = {
                path  : "//staticfile.qnssl.com/emoji-cheat-sheet/1.0.0/", //七牛CDN
                ext   : ".png"
            };
            //]]>
        </script>
        <?php
    }

    public function user_personalopts_update() {
        global $current_user;
        update_user_option($current_user->ID, 'rich_editing', 'false', true);
    }

    //载入JavaScript脚本
    public function add_admin_js() {
        //只在需要有文章编辑器才能加载以下文件
        if (get_current_screen()->base !== 'post') {
            return;
        }
        wp_deregister_script(array('media-upload'));//禁止加载多媒体脚本(减少对编辑器的干扰)
        wp_enqueue_script('jqueryjs', CAT_URL .'/js/jquery.min.js', array(), CAT_VERSION, false);
        wp_enqueue_script('editormdjs', CAT_URL . '/js/editormd.min.js',  array(), CAT_VERSION, false);
    }

    //载入Style样式文件
    public function add_admin_style() {
        //只在需要有文章编辑器才能加载以下文件
        if (get_current_screen()->base !== 'post') {
            return;
        }
        wp_enqueue_style('editormdcss', CAT_URL .'/css/editormd.min.css', array(), CAT_VERSION, 'all');
    }

    public function add_admin_head() {
        ?>
        <style type="text/css">
            .editormd_wrap input#submit {
                border: none;
            }
            #wp-content-editor-container {
                z-index: 99999;
            }
        </style>
        <?php
    }

    function quicktags_settings($qtInit) {
        $qtInit['buttons'] = ' ';
        return $qtInit;
    }

    function plugin_activation() {
        global $wpdb;
        $wpdb->query("UPDATE `" . $wpdb->prefix . "usermeta` SET `meta_value` = 'false' WHERE `meta_key` = 'rich_editing'");
    }

    function plugin_deactivation() {
        global $wpdb;
        $wpdb->query("UPDATE `" . $wpdb->prefix . "usermeta` SET `meta_value` = 'true' WHERE `meta_key` = 'rich_editing'");
    }
}

$editormd = new editormd();

editormd::getInstance();