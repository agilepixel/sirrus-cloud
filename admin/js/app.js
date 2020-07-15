jQuery(function(){

    jQuery('.js-add-sirrus_cloud-field').on('click', function(){
        var target = jQuery(this).data('target');
        var type = jQuery(this).data('type');
        var $target;
        var total = jQuery(this).parents('.wp-list-mapping-table').find('tbody tr').length;

        if(target && jQuery(target).length){
            $target = jQuery(target);
            var $tr = jQuery('<tr class="field field-tr depth-1"></tr>');
            $tr.append('<td class="source source-field" data-colname="source field"><select name="sirrus_cloud_field_'+ type +'[' + total + ']" class="js-select2-sirrus_cloud-field_'+ type +'"></select></td>');
            $tr.append('<td class="wordpress wordpress-field" data-colname="wordpress field"><select name="wp_field_'+ type +'[' + total + ']" class="js-select2-wp-field"></select></td>');
            $tr.append('<td class="field field-option" data-colname="Action"><a href="#" class="js-remove">remove</a></td>');

            applySelect2($tr);
            $target.append($tr);
        }

        return false;
    });

    jQuery('.wp-list-mapping-table').on('click', '.js-remove', function(){
        jQuery(this).parents('.field-tr').remove();

        return false;
    });

    var applySelect2 = function($scope){
        if($scope.find('.js-select2-sirrus_cloud-field_stock').length){
            $scope.find('.js-select2-sirrus_cloud-field_stock').select2({
                tags: true,
                delay: 250,
                placeholder: "Please Select",
                allowClear: false,
                ajax: {
                    cache: false,
                    url: '/wp-admin/admin-ajax.php?action=aimp_source_field&type=stocks',
                    dataType: 'json'
                },
                processResults: function(data){
                     if (data.length){
                        return data;
                    }else{
                        return {
                            results: [{ id: this.$element.data('term'), text: this.$element.data('term') }]
                        };
                    }
                }
            });
        }
        if($scope.find('.js-select2-sirrus_cloud-field_artist').length){
            $scope.find('.js-select2-sirrus_cloud-field_artist').select2({
                tags: true,
                delay: 250,
                placeholder: "Please Select",
                allowClear: false,
                ajax: {
                    cache: false,
                    url: '/wp-admin/admin-ajax.php?action=aimp_source_field&type=artist',
                    dataType: 'json'
                },
                processResults: function(data){
                     if (data.length){
                        return data;
                    }else{
                        return {
                            results: [{ id: this.$element.data('term'), text: this.$element.data('term') }]
                        };
                    }
                }
            });
        }
        if($scope.find('.js-select2-sirrus_cloud-field_group').length){
            $scope.find('.js-select2-sirrus_cloud-field_group').select2({
                tags: true,
                delay: 250,
                placeholder: "Please Select",
                allowClear: false,
                ajax: {
                    cache: false,
                    url: '/wp-admin/admin-ajax.php?action=aimp_source_field&type=group',
                    dataType: 'json'
                },
                processResults: function(data){
                     if (data.length){
                        return data;
                    }else{
                        return {
                            results: [{ id: this.$element.data('term'), text: this.$element.data('term') }]
                        };
                    }
                }
            });
        }
        if($scope.find('.js-select2-wp-field').length){
            $scope.find('.js-select2-wp-field').select2({
                tags: true,
                delay: 250,
                placeholder: "Please Select",
                allowClear: false,
                ajax: {
                    cache: false,
                    url: '/wp-admin/admin-ajax.php?action=aimp_wp_field',
                    dataType: 'json'
                },
                processResults: function (data) {
                     if (data.length){
                        return data;
                    }else{
                        return {
                            results: [{ id: this.$element.data('term'), text: this.$element.data('term') }]
                        };
                    }
                }
            });
        }
        if($scope.find('.js-select2-post_type').length){
            $scope.find('.js-select2-post_type').select2({
                tags: false,
                delay: 250,
                placeholder: "Please Select",
                allowClear: false,
                ajax: {
                    cache: false,
                    url: '/wp-admin/admin-ajax.php?action=aimp_wp_posttype',
                    dataType: 'json'
                },
                processResults: function (data) {
                    return data;
                }
            });
        }
    };

    jQuery('#test-connection').on('click', function(){

        jQuery('#test-connection-notice').remove();
        jQuery.ajax({
            url: ajaxurl + '?action=aimp_test',
            dataType: 'json',
            success: function(json){
                var html = '';
                if(json.success){
                    html = '<div id="test-connection-notice" class="updated notice-success is-dismissible"><p>' + json.message + '</p></div>';
                }else{
                    html = '<div id="test-connection-notice" class="notice notice-error is-dismissible"><p>' + json.message + '</p></div>';
                }

                jQuery('.wrap > h1').after(html);
            }
        });

        return false
    });

    applySelect2(jQuery('#wpbody-content'));
});