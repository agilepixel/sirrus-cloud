<div class="wrap mapping_form">
    <h1>Data Mapping</h1>
    <h2>Stock Field</h2>
    <form action="" method="post">
        <table class="wp-list-table wp-list-mapping-table widefat fixed striped comments">
            <thead>
                <tr>
                    <th scope="col" width="40%" id="stock-source-field" class="manage-column column-field column-primary">Source Field</th>
                    <th scope="col" width="40%" id="stock-wordpress-field" class="manage-column column-field column-primary">Wordpress Field</th>
                    <th scope="col" width="20%" id="stock-field-option" class="manage-column column-field column-option"><a href="#" id="add-stock-field" class="js-add-sirrus_cloud-field button button-primary" data-target="#stock-field-list" data-type="stock"><?=__('Add new field', 'sirrus_cloud')?></a></th>
                </tr>
            </thead>
            <tbody id="stock-field-list" data-wp-lists="list:stock-field">
                <?php if (!empty($sirrus_cloud_mapping_stock)):?>
                    <?php $_index=0; foreach ($sirrus_cloud_mapping_stock as $_source => $_wp):?>
                        <tr class="field field-tr depth-1">
                            <td class="source source-field" data-colname="source field">
                                <select name="sirrus_cloud_field_stock[<?=$_index?>]" class="js-select2-sirrus_cloud-field_stock">
                                    <option value="<?=$_source?>" selected><?=$_source?></option>
                                </select>
                            </td>
                            <td class="wordpress wordpress-field" data-colname="wordpress field">
                                <select name="wp_field_stock[<?=$_index?>]" class="js-select2-wp-field">
                                    <option value="<?=$_wp?>" selected><?=$_wp?></option>
                                </select>
                            </td>
                            <td class="field field-option" data-colname="Action"><a href="#" class="js-remove">remove</a></td>
                        </tr>
                    <?php $_index++;endforeach;?>
                <?php endif;?>
            </tbody>
        </table>
        <?php submit_button('Save Settings');?>
        <input type="hidden" name="settings-stock" value="1">
    </form>
    <h2>Artist Field</h2>
    <form action="" method="post">
        <table class="wp-list-table wp-list-mapping-table widefat fixed striped comments">
            <thead>
                <tr>
                    <th scope="col" width="40%" id="artist-source-field" class="manage-column column-field column-primary">Source Field</th>
                    <th scope="col" width="40%" id="artist-wordpress-field" class="manage-column column-field column-primary">Wordpress Field</th>
                    <th scope="col" width="20%" id="artist-field-option" class="manage-column column-field column-option"><a href="#" id="add-stock-field" class="js-add-sirrus_cloud-field button button-primary" data-target="#artist-field-list" data-type="artist"><?=__('Add new field', 'sirrus_cloud')?></a></th>
                </tr>
            </thead>
            <tbody id="artist-field-list" data-wp-lists="list:artist-field">
                <?php if (!empty($sirrus_cloud_mapping_artist)):?>
                    <?php $_index=0; foreach ($sirrus_cloud_mapping_artist as $_source => $_wp):?>
                        <tr class="field field-tr depth-1">
                            <td class="source source-field" data-colname="source field">
                                <select name="sirrus_cloud_field_artist[<?=$_index?>]" class="js-select2-sirrus_cloud-field_artist">
                                    <option value="<?=$_source?>" selected><?=$_source?></option>
                                </select>
                            </td>
                            <td class="wordpress wordpress-field" data-colname="wordpress field">
                                <select name="wp_field_artist[<?=$_index?>]" class="js-select2-wp-field">
                                    <option value="<?=$_wp?>" selected><?=$_wp?></option>
                                </select>
                            </td>
                            <td class="field field-option" data-colname="Action"><a href="#" class="js-remove">remove</a></td>
                        </tr>
                    <?php $_index++;endforeach;?>
                <?php endif;?>
            </tbody>
        </table>
        <?php submit_button('Save Settings');?>
        <input type="hidden" name="settings-artist" value="1">
    </form>
    <h2>Group Field</h2>
    <form action="" method="post">
        <table class="wp-list-table wp-list-mapping-table widefat fixed striped comments">
            <thead>
                <tr>
                    <th scope="col" width="40%" id="group-source-field" class="manage-column column-field column-primary">Source Field</th>
                    <th scope="col" width="40%" id="group-wordpress-field" class="manage-column column-field column-primary">Wordpress Field</th>
                    <th scope="col" width="20%" id="group-field-option" class="manage-column column-field column-option"><a href="#" id="add-stock-field" class="js-add-sirrus_cloud-field button button-primary" data-target="#group-field-list" data-type="group"><?=__('Add new field', 'sirrus_cloud')?></a></th>
                </tr>
            </thead>
            <tbody id="group-field-list" data-wp-lists="list:group-field">
                <?php if (!empty($sirrus_cloud_mapping_group)):?>
                    <?php $_index=0; foreach ($sirrus_cloud_mapping_group as $_source => $_wp):?>
                        <tr class="field field-tr depth-1">
                            <td class="source source-field" data-colname="source field">
                                <select name="sirrus_cloud_field_group[<?=$_index?>]" class="js-select2-sirrus_cloud-field_group">
                                    <option value="<?=$_source?>" selected><?=$_source?></option>
                                </select>
                            </td>
                            <td class="wordpress wordpress-field" data-colname="wordpress field">
                                <select name="wp_field_group[<?=$_index?>]" class="js-select2-wp-field">
                                    <option value="<?=$_wp?>" selected><?=$_wp?></option>
                                </select>
                            </td>
                            <td class="field field-option" data-colname="Action"><a href="#" class="js-remove">remove</a></td>
                        </tr>
                    <?php $_index++;endforeach;?>
                <?php endif;?>
            </tbody>
        </table>
        <?php submit_button('Save Settings');?>
        <input type="hidden" name="settings-group" value="1">
    </form>
</div>


<script></script>