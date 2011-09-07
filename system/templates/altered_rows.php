		->update(
			array(
<?php foreach ($changed_columns as $changed_column => $changed_val): ?>
				<?php echo sprintf('\'%s\' => %s,', $changed_column, sql::escape($changed_val)), PHP_EOL; ?>
<?php endforeach; ?>
			),
			array(
				<?php echo sprintf('\'%s\' => %s,', $primary_column, sql::escape($primary_val)), PHP_EOL; ?>
			)
		)
