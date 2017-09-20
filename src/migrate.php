<?php
	//Configuration
	$migrationDir = realpath(getenv("MIGRATION_DIR") ?: "migrations") . "/";
	$host = getenv("MYSQL_HOST") ?: "127.0.0.1";
	$database = getenv("MYSQL_DATABASE");
	$username = getenv("MYSQL_USER") ?: "root";
	$password = getenv("MYSQL_PASSWORD") ?: "";
	$table = getenv("MYSQL_TABLE") ?: "migrations";

	//Connect to database
	$pdo = new PDO("mysql:dbname=$database;host=$host", $username, $password);

	//Retrieve installed migrations
	$installedIdsStatement = $pdo->query("SELECT id FROM $table");
	if ($installedIdsStatement === false) {
		$pdo->query("CREATE TABLE $table(id VARCHAR(255) NOT NULL PRIMARY KEY, down TEXT NOT NULL)");
		$installedIds = [];
	} else
		$installedIds = $installedIdsStatement->fetchAll(PDO::FETCH_COLUMN);

	//Retrieve available migrations
	$availableIds = glob($migrationDir . "*-up.sql");
	foreach ($availableIds as &$value)
		$value = substr(substr($value, 0, -7), strlen($migrationDir));

	//Downgrade
	$uninstallIds = array_diff($installedIds, $availableIds);
	rsort($uninstallIds);
	$selectStatement = $pdo->prepare("SELECT down FROM $table WHERE id = :id");
	$deleteStatement = $pdo->prepare("DELETE FROM $table WHERE id = :id");
	foreach ($uninstallIds as $id)
		$selectStatement->execute([":id" => $id]) and
		$pdo->query($selectStatement->fetchColumn()) and
		$deleteStatement->execute([":id" => $id]);

	//Upgrade
	$installIds = array_diff($availableIds, $installedIds);
	sort($installIds);
	$insertStatement = $pdo->prepare("INSERT INTO $table(id, down) VALUES (:id, :down)");
	foreach ($installIds as $id)
		$pdo->query(file_get_contents($migrationDir . $id . "-up.sql")) and
		$insertStatement->execute([":id" => $id, ":down" => file_get_contents($migrationDir . $id . "-down.sql")]);
?>
