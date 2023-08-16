<?php

namespace OXI_IMAGE_HOVER_PLUGINS\Modules\Lightbox\Render;

if (!defined('ABSPATH')) {
    exit;
}

use OXI_IMAGE_HOVER_PLUGINS\Page\Public_Render;

class Effects2 extends Public_Render {

    public function public_css() {
        wp_enqueue_style('oxi-image-hover-light-box', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/Lightbox.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        wp_enqueue_style('oxi-image-hover-light-style-2', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/style-2.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        wp_enqueue_style('OxiPopup', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/Popup.css', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
    }

    public function public_jquery() {
        wp_enqueue_script('OxiPopup', OXI_IMAGE_HOVER_URL . '/Modules/Lightbox/Files/Popup.js', false, OXI_IMAGE_HOVER_PLUGIN_VERSION);
        $this->JSHANDLE = 'OxiPopup';
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
            <div class="oxi_addons__light_box_style_2 oxi_addons__light_box <?php $this->column_render('oxi-image-hover-col', $style) ?>  <?php
            echo ($admin == "admin" ? 'oxi-addons-admin-edit-list' : '');
            ?>"> 
                <div class="oxi_addons__light_box_parent"> 
                    <?php
                    if ($this->custom_media_render('oxi_image_light_box_image', $value) != '') {
                        ?>
                    <div class="oxi_addons__light_box_item  lightbox_key_<?php echo (int) $key; ?>" <?php
                        if (array_key_exists('oxi_image_light_box_clickable', $style) && $style['oxi_image_light_box_clickable'] == 'image'):
                            echo ' style="width: 100%" ';
                        endif;
                        ?>>  
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
                                    <div  class="oxi_addons__image_main <?php echo esc_attr($style['oxi_image_light_box_custom_width_height_swither']); ?>" style="background-image: url(<?php echo esc_url($this->custom_media_render('oxi_image_light_box_image_front', $value)); ?>);" >
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
                                            <?php $this->font_awesome_render($style['oxi_image_light_box_bg_overlay_icon_icon']) ?>
                                        </div>
                                        <?php $this->font_awesome_render($value['oxi_image_light_box_button_icon']); ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>  
                </div>
                <?php
                if ($admin == 'admin') :
                    $this->oxi_addons_admin_edit_delete_clone($val['id']);
                endif;
                ?>
            </div> 
            <?php
        }
    }

    public function inline_public_jquery() {
        $js = '';
        $child = $this->child;

        foreach ($child as $key => $val) {
            $value = json_decode(stripslashes($val['rawdata']), true);

            $image_video = '';
            if ($value['oxi_image_light_box_select_type'] == 'image') {
                $image_video = 'items: [
                    {
                        src: "' . $this->custom_media_render('oxi_image_light_box_image', $value) . '",
                    }
                ],
                type: "image",';
            } else {
                $image_video = 'items: [
                    {
                        src: "' . $value['oxi_image_light_box_video'] . '",
                    }
                ],
                type: "iframe",';
            }


            $js .= 'jQuery(".' . $this->WRAPPER . ' .lightbox_key_' . $key . '").OxizenPopup({
                        ' . $image_video . '
                        mainClass: "' . $this->WRAPPER . '",
                        callbacks: {
                                    beforeChange: function() {
                                     this.items[0].src = this.items[0].src + "?=" + Math.random(); 
                                    }
                        },  
                        closeBtnInside: true,
                        closeOnContentClick: true,
                        tLoading: "",
                    });';
        }
        return $js;
    }

    public function inline_public_css() {
        $style = $this->style;
        return '.oxi-image-hover-wrapper-' . $this->oxiid . '.Oxipopup-bg{
                        background: ' . $style['oxi_image_light_bg_color'] . ';
                        z-index: ' . ($style['oxi_image_light_z_ind'] - 1) . ';
                      }
                  .oxi-image-hover-wrapper-' . $this->oxiid . '.Oxipopup-wrap{
                   z-index: ' . $style['oxi_image_light_z_ind'] . ';
                  }
                  .oxi-image-hover-wrapper-' . $this->oxiid . ' .Oxipopup-content{
                    z-index: ' . ($style['oxi_image_light_z_ind'] + 2) . ';
                    }
                  .oxi-image-hover-wrapper-' . $this->oxiid . ' .Oxipopup-close{
                      z-index: ' . ($style['oxi_image_light_z_ind'] + 3) . ';
                  }

                 .oxi-image-hover-wrapper-' . $this->oxiid . ' .Oxipopup-image-holder .Oxipopup-close, 
                 .oxi-image-hover-wrapper-' . $this->oxiid . ' .Oxipopup-iframe-holder .Oxipopup-close{
                     color: ' . $style['oxi_image_light_cls_clr'] . ';
                  }
                 
                  .oxi-image-hover-wrapper-' . $this->oxiid . ' .Oxipopup-preloader{
                     background: ' . $style['oxi_image_light_pre_clr'] . ';
                  }';
    }

}
