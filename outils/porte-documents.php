<?php

class Porte_Documents extends BP_Group_Extension {

	function porte_documents() {
		$this->name = 'Porte-documents';
		$this->slug = 'porte-documents';
		$this->create_step_position = 21;
		$this->nav_item_position = 1000;
	}
	
	function create_screen() {
		echo "toto";
	}

	/* Vue onglet admin */
	function edit_screen() {
		if ( !bp_is_group_admin_screen( $this->slug ) )
		return false; ?>
		<input type="radio" name="admin-outil" value="true" />Afficher
		<input type="radio" name="admin-outil" value="false" />Masquer
		<p>
			<input type="submit" class="btn-gray" value="<?php _e( 'Save Changes', 'huddle' ) ?>" id="save" name="save" />
		</p>
		<?php
		wp_nonce_field( 'groups_edit_save_' . $this->slug );
		do_action( 'bp_after_group_settings_admin' );
	}

	function edit_screen_save() {
		global $bp;
		if ( !isset( $_POST ) )
		return false;
		check_admin_referer( 'groups_edit_save_' . $this->slug );
		/* Insert your edit screen save code here */
		/* To post an error/success message to the screen, use the following */
		if ( !$success )
		bp_core_add_message( __( 'There was an error saving, please try again', 'buddypress' ), 'error' );
		else
		bp_core_add_message( __( 'Settings saved successfully', 'buddypress' ) );
		bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . '/admin/' . $this->slug );
	}

	/* Vue onglet principal */
	function display() {
		echo "Projet ".bp_get_group_name().' n°'.bp_get_group_id();
	}
	
}

bp_register_group_extension( 'Porte_Documents' );


?>
