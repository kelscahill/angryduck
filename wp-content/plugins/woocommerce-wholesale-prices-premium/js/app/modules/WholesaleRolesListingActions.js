var wwppWholesaleRolesListingActions = function(){

    var $wholesaleRolesTable = jQuery("#wwpp-wholesale-roles-page").find(".wp-list-table"),
        $wholesaleRolesList = $wholesaleRolesTable.find("#the-list"),
        refreshRowClasses = function(){

            $wholesaleRolesList.find("tr").each(function(index){

                if((index + 1) % 2 != 0){

                    jQuery(this).addClass('alternate');

                }else{

                    jQuery(this).removeClass('alternate');

                }

            });

        },
        removeNewlyAddedRowClasses = function(){

            setTimeout(function(){
                $wholesaleRolesList
                    .find('.newlyAdded')
                    .removeClass('newlyAdded');
            },3000);

        },
        setRowToEditMode = function($row){

            $row.addClass("editing");

        },
        setRowsToNormalMode = function(){

            $wholesaleRolesList.find("tr").removeClass("editing");

        },
        incrementRolesCount = function(){

            $wholesaleRolesTable.siblings(".tablenav").find(".wholesale-roles-count").each(function(){

                jQuery(this).text(parseInt(jQuery(this).text(),10) + 1);

            });

        },
        decrementRolesCount = function(){

            $wholesaleRolesTable.siblings(".tablenav").find(".wholesale-roles-count").each(function(){

                if(parseInt(jQuery(this).text(),10) > 0)
                    jQuery(this).text(parseInt(jQuery(this).text(),10) - 1);

            });

        },
        addRole = function(newRole){

            var i18n_wholesale_pruchases_only = newRole[ 'onlyAllowWholesalePurchases' ] === 'yes' ? wwpp_wholesaleRolesListingActions_params.i18n_yes : wwpp_wholesaleRolesListingActions_params.i18n_no;
            var newRow =    '<tr id="' + newRole[ 'roleKey' ] + '" class="newlyAdded">' +
                                '<td class="role-name column-role-name">' +
                                    '<strong><a>'+newRole['roleName']+'</a></strong><br>' +
                                    '<div class="row-actions">' +
                                        '<span class="edit"><a class="edit-role" href="#">' + wwpp_wholesaleRolesListingActions_params.i18n_edit + '</a> | </span>' +
                                        '<span class="delete"><a class="delete-role" href="#">' + wwpp_wholesaleRolesListingActions_params.i18n_delete + '</a></span>' +
                                    '</div>' +
                                '</td>' +
                                '<td class="role-key column-role-key">' + newRole[ 'roleKey' ] + '</td>' +
                                '<td class="only-allow-wholesale-purchases column-only-allow-wholesale-purchases" data-attr-raw-data="' + newRole[ 'onlyAllowWholesalePurchases' ] + '">' + i18n_wholesale_pruchases_only + '</td>' +
                                '<td class="role-desc column-role-desc">'+ newRole[ 'roleDesc' ] + '</td>' +
                            '</tr>';

            $wholesaleRolesList.append(newRow);

            incrementRolesCount();

            refreshRowClasses();

            removeNewlyAddedRowClasses();

        },
        editRole = function(role){

            var i18n_wholesale_pruchases_only = role[ 'onlyAllowWholesalePurchases' ] === 'yes' ? wwpp_wholesaleRolesListingActions_params.i18n_yes : wwpp_wholesaleRolesListingActions_params.i18n_no;            
            var currentRow = $wholesaleRolesList.find('.column-role-key').filter( function() {

                return jQuery( this ).text() === role[ 'roleKey' ];

            } ).closest( 'tr' );

            currentRow.find( '.column-role-name' ).find( 'strong' ).find( 'a' ).text( role[ 'roleName' ] );
            currentRow.find( '.column-only-allow-wholesale-purchases' ).attr( 'data-attr-raw-data' , role[ 'onlyAllowWholesalePurchases' ] ).text( i18n_wholesale_pruchases_only );            
            currentRow.find( '.column-role-desc' ).text( role[ 'roleDesc' ] );
            currentRow.addClass( 'newlyAdded' );

            removeNewlyAddedRowClasses();

        },
        deleteRole = function(roleKey){

            $wholesaleRolesList.find('.column-role-key').filter(function(){

                return jQuery(this).text() === roleKey;

            }).closest('tr').remove();

            decrementRolesCount();
            refreshRowClasses();

        };

    return {
        addRole             :   addRole,
        editRole            :   editRole,
        deleteRole          :   deleteRole,
        setRowToEditMode    :   setRowToEditMode,
        setRowsToNormalMode :   setRowsToNormalMode
    };

}();