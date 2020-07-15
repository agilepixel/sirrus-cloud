<div class="wrap">
    <h1>Import Settings</h1>
    <form action="" method="post">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="import_endpoint"><?=__('Connector Domain', 'sirrus_cloud')?></label></th>
                    <td>
                        <input name="import_endpoint" type="text" id="import_endpoint" value="<?=$fields_value['import_endpoint']?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="import_username"><?=__('Connector Username', 'sirrus_cloud')?></label></th>
                    <td>
                        <input name="import_username" type="text" id="import_username" value="<?=$fields_value['import_username']?>" class="regular-text" placeholder="api-upload-username">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="import_password"><?=__('Connector Password', 'sirrus_cloud')?></label></th>
                    <td>
                        <input name="import_password" type="password" id="import_password" value="<?=$fields_value['import_password']?>" class="regular-text" placeholder="api-upload-password">
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><h2 style="margin:0;">Import mapping</h2></th>
                </tr>
                <tr>
                    <th scope="row"><label for="stock_link_post_type"><?=__('Stock ', 'sirrus_cloud')?></label></th>
                    <td class="select-post_type">
                         <select name="import_stock_link_post_type" id="stock_link_post_type" class="js-select2-post_type">
                            <?=$fields_value['import_stock_link_post_type_selected']?>
                         </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="artist_link_post_type"><?=__('Artist ', 'sirrus_cloud')?></label></th>
                    <td class="select-post_type">
                         <select name="import_artist_link_post_type" id="artist_link_post_type" class="js-select2-post_type">
                             <?=$fields_value['import_artist_link_post_type_selected']?>
                         </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="group_link_post_type"><?=__('Group ', 'sirrus_cloud')?></label></th>
                    <td class="select-post_type">
                         <select name="import_group_link_post_type" id="group_link_post_type" class="js-select2-post_type">
                             <?=$fields_value['import_group_link_post_type_selected']?>
                         </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="additional_groups"><?=__('Additional Groups', 'sirrus_cloud')?></label></th>
                    <td>
                        <input name="additional_groups" type="number" id="additional_groups" value="<?=$fields_value['additional_groups']?>" class="regular-text">
                    </td>
                </tr>
                <?php for($x = 1; $x <= $fields_value['additional_groups'] ; $x++): ?>
                <tr>
                    <th scope="row"><label for="group_link_post_type_custom[<?=$x?>]"><?=__('Custom Group ', 'sirrus_cloud')?> <?=$x?></label>
                    <input name="import_group_link_custom[<?=$x?>]" type="text" id="group_link_custom[<?=$x?>]" value="<?=$fields_value['import_group_link_custom'][$x]?>" class="regular-text" placeholder="Type Group Name Here">
                    </th>
                    <td class="select-post_type">
                         <select name="import_group_link_post_type_custom[<?=$x?>]" id="group_link_post_type_custom[<?=$x?>]" class="js-select2-post_type">
                            <option value="<?=$fields_value['import_group_link_post_type_custom'][$x]?>"><?=$fields_value['import_group_link_post_type_custom'][$x]?></option>
                         </select>
                    </td>
                </tr>
                <?php endfor ?>
            </tbody>
        </table>
        <p>
            <?php submit_button('Save Settings', 'primary large', 'submit', false);?>&nbsp;&nbsp;&nbsp;<button id="test-connection" class="button button-secondary button-large">Test Connection</button>
        </p>
        <input type="hidden" name="settings-updated" value="1">
    </form>
</div>


