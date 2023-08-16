<?php

namespace OXI_IMAGE_HOVER_PLUGINS\Modules\Lightbox\Render;

if (!defined('ABSPATH')) {
    exit;
}

use OXI_IMAGE_HOVER_PLUGINS\Page\Public_Render;

class Effects1 extends Public_Render {

    public function public_css() {
        wp_enqueue_style('oxi-image-hover-light-box', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/Lightbox.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        wp_enqueue_style('oxi-image-hover-light-style-1', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/style-1.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        wp_enqueue_style('oxi_addons__light_box_style_1', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/lightgallery.min.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
    }

    public function public_jquery() {
        wp_enqueue_script('oxi_addons__light_box_picturefill', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/picturefill.min.js', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        $this->JSHANDLE = 'oxi_addons__light_box_picturefill';
        wp_enqueue_script('oxi_addons__light_box_lightgallery_all', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/lightgallery_all.min.js', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        $this->JSHANDLE = 'oxi_addons__light_box_lightgallery_all';
        wp_enqueue_script('oxi_addons__light_box_mousewheel', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/jquery.mousewheel.min.js', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        $this->JSHANDLE = 'oxi_addons__light_box_mousewheel';
    }

    /*
     * Shortcode Addons Media Render.
     * image
     * @since 2.1.0
     */

    public function custom_media_render($id, $style) {
        $url = '';
        if (array_key_exists($id . '-select', $style)):
            if ($style[$id . '-select'] == 'media-library'):
                return $style[$id . '-image'];
            else:
                return $style[$id . '-url'];
            endif;
        endif;
    }

    public function default_render($style, $child, $admin) {

        foreach ($child as $key => $val) {
            $value = json_decode(stripslashes($val['rawdata']), true);
            ?>

            <div class="oxi_addons__light_box_style_1 oxi_addons__light_box <?php $this->column_render('oxi-image-hover-col', $style) ?> <?php
            if ($admin == "admin"):
                echo 'oxi-addons-admin-edit-list';
            endif;
            ?> ">

                <div class="oxi_addons__light_box_parent oxi_addons__light_box_parent-<?php echo (int) $this->oxiid ?>-<?php echo (int) $key; ?>">
                    <?php
                    if ($value['oxi_image_light_box_select_type'] == 'image' && $this->custom_media_render('oxi_image_light_box_image', $value) != '') {
                        ?>
                        <div class="oxi_addons__light_box_item" <?php
                        if (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'image'):
                            echo ' style="width: 100%" ';
                        endif;
                        ?> data-src="<?php echo esc_url($this->custom_media_render('oxi_image_light_box_image', $value)); ?>"  data-sub-html="<?php
                             if (array_key_exists('oxi_image_light_box_title', $value) && $value['oxi_image_light_box_title'] != '') {
                                 ?>
                                 <<?php echo esc_attr($style['oxi_image_light_box_tag']); ?> class='oxi_addons__heading'><?php $this->text_render($value['oxi_image_light_box_title']) ?></<?php echo esc_attr($style['oxi_image_light_box_tag']); ?>>
                                 <?php
                             }
                             ?> <br> <?php
                             if (array_key_exists('oxi_image_light_box_desc', $value) && $value['oxi_image_light_box_desc'] != '') {
                                 ?>
                                 <div class='oxi_addons__details'><?php $this->text_render($value['oxi_image_light_box_desc']) ?></div>
                                 <?php
                             }
                             ?>">
                                 <?php
                                 if (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'button') {
                                     if (array_key_exists('oxi_image_light_box_button_text', $value) && $value['oxi_image_light_box_button_text'] != '') {
                                         ?>
                                    <div class="oxi_addons__button_main">
                                        <button class="oxi_addons__button">
                                            <?php $this->text_render($value['oxi_image_light_box_button_text']) ?>
                                        </button>
                                    </div>
                                    <?php
                                }
                            } elseif (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'image') {
                                if ($this->custom_media_render('oxi_image_light_box_image_front', $value) != '') {
                                    ?>
                                    <div  class="oxi_addons__image_main  <?php echo esc_attr($style['oxi_image_light_box_custom_width_height_swither']); ?>" style="background-image: url('<?php echo esc_url($this->custom_media_render('oxi_image_light_box_image_front', $value)); ?>');" >
                                        <div class="oxi_addons__overlay">
                                            <?php $this->font_awesome_render($style['oxi_image_light_box_bg_overlay_icon']); ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                if ($value['oxi_image_light_box_button_icon'] != '') {
                                    ?>
                                    <div  class="oxi_addons__icon" >
                                        <div class="oxi_addons__overlay">
                                            <?php $this->font_awesome_render($style['oxi_image_light_box_bg_overlay_icon_icon']); ?>
                                        </div>
                                        <?php $this->font_awesome_render($value['oxi_image_light_box_button_icon']) ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                    } else {
                        ?>

                        <a class="oxi_addons__light_box_item" data-src="<?php echo esc_url($value['oxi_image_light_box_video']); ?>" data-sub-html="<?php
                        if (array_key_exists('oxi_image_light_box_title', $value) && $value['oxi_image_light_box_title'] != '') {
                            ?>
                               <<?php echo esc_attr($style['oxi_image_light_box_tag']); ?> class='oxi_addons__heading'><?php $this->text_render($value['oxi_image_light_box_title']) ?></<?php echo esc_attr($style['oxi_image_light_box_tag']); ?>>
                               <?php
                           }
                           ?> <br> <?php
                           if (array_key_exists('oxi_image_light_box_desc', $value) && $value['oxi_image_light_box_desc'] != '') {
                               ?>
                               <div class='oxi_addons__details'><?php $this->text_render($value['oxi_image_light_box_desc']) ?></div>
                               <?php
                           }
                           ?>">
                               <?php
                               if (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'button') {
                                   if (array_key_exists('oxi_image_light_box_button_text', $value) && $value['oxi_image_light_box_button_text'] != '') {
                                       ?>
                                    <div class="oxi_addons__button_main">
                                        <button class="oxi_addons__button">
                                            <?php $this->text_render($value['oxi_image_light_box_button_text']); ?>
                                        </button>
                                    </div>
                                    <?php
                                }
                            } elseif (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'image') {
                                if ($this->custom_media_render('oxi_image_light_box_image_front', $value) != '') {
                                    ?>
                                    <div  class="oxi_addons__image_main <?php echo esc_attr($style['oxi_image_light_box_custom_width_height_swither']); ?>" style="background-image: url('<?php echo esc_url($this->custom_media_render('oxi_image_light_box_image_front', $value)); ?>');" >
                                        <div class="oxi_addons__overlay">
                                            <?php $this->font_awesome_render($style['oxi_image_light_box_bg_overlay_icon']); ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                if ($value['oxi_image_light_box_button_icon'] != '') {
                                    ?>
                                    <div  class="oxi_addons__icon" >
                                        <div class="oxi_addons__overlay">
                                            <?php $this->font_awesome_render($style['oxi_image_light_box_bg_overlay_icon_icon']); ?>
                                        </div>
                                        <?php $this->font_awesome_render($value['oxi_image_light_box_button_icon']); ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </a>
                        <?php
                    }
                    ?>
                </div>
                <?php
                if ($admin == 'admin'):
                    $this->oxi_addons_admin_edit_delete_clone($val['id']);
                endif;
                ?>
            </div>
            <?php
        }
    }

    public function inline_public_jquery() {
        $jquery = '';
        $child = $this->child;
        foreach ($child as $key => $val) {
            $value = json_decode(stripslashes($val['rawdata']), true);
            $jquery .= 'jQuery(".' . $this->WRAPPER . ' .oxi_addons__light_box_parent-' . $this->oxiid . '-' . $key . '").lightGallery({
                share: false,
                addClass: "oxi_addons_light_box_overlay_' . $this->oxiid . '"
            });';
        }

        return $jquery;
    }

}
