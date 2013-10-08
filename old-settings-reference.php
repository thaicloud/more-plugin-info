<div class="wrap">
<h2>More Plugin Info Settings</h2>
	<h3>Data Sync Settings</h3>
	<p>In order to display accurate data, you should sync your plugin data from time to time. </p>
	<p>Your plugin data was last updated: <strong><?php echo get_option('mpi_sync_timestamp', 'Never'); ?></strong></p>
			
	<table class="form-table">
	<tbody>	
	<form name="mpi_sync_form" method="post" action="plugins.php?mpi_sync">
		<tr valign="top">
			<td> 
				<input type="submit" name="Submit" class="button-primary" value="Update Plugin Data Now" />
				 ( This may take a couple of minutes )<p>
			</td>
		</tr>
	</form>
	</tbody>
	</table>
	
	<h3>Display Settings</h3>
	<p>
		Select which fields should appear in the plugins listing.
	</p>
	<table class="form-table">
	<tbody>	
	<form name="mpi_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="submit_check" value="Y">  
		<tr valign="top">
			<th scope="row">
				<label for="mpi_downloads">Number of Downloads:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_downloads" <?php if(!empty($downloads)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_rating">Rating:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_rating" <?php if(!empty($rating)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_num_ratings">Number of Ratings:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_num_ratings" <?php if(!empty($num_ratings)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_added">Date Added:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_added" <?php if(!empty($added)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_updated">Last Updated Date:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_updated" <?php if(!empty($updated)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_requires">Requires Version:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_requires" <?php if(!empty($requires)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_tested">Tested Version:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_tested" <?php if(!empty($tested)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_donate_link">Donate Link:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_donate_link" <?php if(!empty($donate_link)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_download_link">Download Link:</label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_download_link" <?php if(!empty($download_link)){ echo ' checked'; } ?> />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="mpi_realtime"><strong>Auto-sync (not recommended):</strong></label>
			</th>
			<td> 
				<input type="checkbox" name="mpi_realtime" <?php if(!empty($realtime)){ echo ' checked'; } ?> />
			</td>
		</tr>
		
		<tr valign="top">
			<td> 
				<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
			</td>
		</tr>
	</form>
	</tbody>
	</table>
</div>