<?php
/**
 * Created by IntelliJ IDEA.
 * User: chenzhidong
 * Date: 13-10-21
 * Time: 下午7:20
 * Version: 1.0
 */

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (isset($_POST['submit'])) {
	if (isset($_POST['action']) && $_POST['action'] == 'diff') {
		$new_host = trim($_POST['new_host']);
		$new_user = trim($_POST['new_user']);
		$new_pass = trim($_POST['new_pass']);
		$new_db = trim($_POST['new_db']);

		$old_host = trim($_POST['old_host']);
		$old_user = trim($_POST['old_user']);
		$old_pass = trim($_POST['old_pass']);
		$old_db = trim($_POST['old_db']);

		if (!empty($new_host) && !empty($new_user) && !empty($new_pass) && !empty($new_db) && !empty($old_host) && !empty($old_user) && !empty($old_pass) && !empty($old_db)) {
			$new_errors = array();
			$old_errors = array();
			$new = get_db_detail($new_host, $new_user, $new_pass, $new_db, $new_errors);
			$old = get_db_detail($old_host, $old_user, $old_pass, $old_db, $old_errors);
			if (!empty($new_errors) || !empty($old_errors))
				showform($_POST, array_merge($new_errors, $old_errors));
			else {
				$diff = compare_database($new, $old);
				if (empty($diff['table']) && empty($diff['field']) && empty($diff['index'])) {
					showform($_POST, array('两个数据库结构完全相同，无可同步项目'));
				}
				else {
					$sqls = build_query($diff);
					showdiff($old_host, $old_user, $old_pass, $old_db, $sqls);
				}
			}
		}
		else {
			showform($_POST, array('请补全新旧服务器连接信息'));
		}
	}
	elseif (isset($_POST['action']) && $_POST['action'] == 'write') {
		$old_host = trim($_POST['old_host']);
		$old_user = trim($_POST['old_user']);
		$old_pass = trim($_POST['old_pass']);
		$old_db = trim($_POST['old_db']);
		$sqls = unserialize(trim(html_entity_decode($_POST['sqls'])));
		if (!isset($_POST['keys'])) {
			showdiff($old_host, $old_user, $old_pass, $old_db, $sqls, array('未选择执行的修改'));
		}
		else {
			$keys = $_POST['keys'];
			if (!empty($old_host) && !empty($old_user) && !empty($old_pass) && !empty($old_db) && !empty($keys) && is_array($keys) && !empty($sqls) && is_array($sqls)) {
				$errors = array();
				$chosen = array();
				foreach ($keys as $key)
					$chosen[] = $sqls[$key];
				$result = update_db($old_host, $old_user, $old_pass, $old_db, $chosen, $errors);
				if (!empty($errors)) {
					showdiff($old_host, $old_user, $old_pass, $old_db, $sqls, $errors, $keys);
				}
				else {
					showform(null, array('同步成功'));
				}
			}
			else {
				showdiff($old_host, $old_user, $old_pass, $old_db, $sqls);
			}
		}
	}
	else {
		showform($_POST);
	}
}
else {
	showform();
}

function getheader($title) {
	echo <<<EOC
<html>
<head>
<title>$title —— MySQL_DB_Diff By 傻东</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style type="text/css">
body{background-color:#FFFFDD;}
textarea{width:300px;height:100px;}
.title{padding-top:20px;padding-bottom:35px;text-align:center;font-weight:bold;font-size:14px;}
/* .submit{margin:8px;font-weight:bold;font-size:13px;width:515px;text-align:right;} */
.note{margin:8px;font-weight:bold;font-size:13px;}
.red{color:red;}
.inside{padding:8px;}
.products-list{width:100%;}
.product{width:100%;padding:8px;border-top:1px solid black;}
.border_red{border: 1px solid red;}
.short{width:40px;}
.middle{width:120px;}
.long{width:360px;}
</style>
</head>
<body>
EOC;
}

function getfooter() {
	echo <<<EOD
</body>
</html>
EOD;
}

function showform($post = null, $errors = null) {
	getheader('同步数据库');
	$error_html = "<div>";
	if (!empty($errors)) {
		foreach ($errors as $error) {
			$error_html .= "<p class='red'>" . $error . "</p>";
		}
	}
	$error_html .= "</div>";

	$form_html = '
<div style="margin:20px 200px 30px 200px;">
	<div class="title">同步数据库 —— MySQL_DB_Diff</div>
	' . $error_html . '
	<form method="POST">
		<table width="100%" border="0" cellpadding="5" cellspacing="0">
			<tr>
				<td>新服务器地址:</td>
				<td><input name="new_host" type="text" value="' . (isset($post['new_host']) ? $post['new_host'] : '') . '" size="50" autocomplete="off" /></td>
				<td>旧服务器地址:</td>
				<td><input name="old_host" type="text" value="' . (isset($post['old_host']) ? $post['old_host'] : '') . '" size="50" autocomplete="off" /></td>
			</tr>
			<tr>
				<td>新服务器用户名:</td>
				<td><input name="new_user" type="text" value="' . (isset($post['new_user']) ? $post['new_user'] : '') . '" size="50" autocomplete="off" /></td>
				<td>旧服务器用户名:</td>
				<td><input name="old_user" type="text" value="' . (isset($post['old_user']) ? $post['old_user'] : '') . '" size="50" autocomplete="off" /></td>
			</tr>
			<tr>
				<td>新服务器密码:</td>
				<td><input name="new_pass" type="password" value="' . (isset($post['new_pass']) ? $post['new_pass'] : '') . '" size="50" autocomplete="off" /></td>
				<td>旧服务器密码:</td>
				<td><input name="old_pass" type="password" value="' . (isset($post['old_pass']) ? $post['old_pass'] : '') . '" size="50" autocomplete="off" /></td>
			</tr>
			<tr>
				<td>新服务器数据库名:</td>
				<td><input name="new_db" type="text" value="' . (isset($post['new_db']) ? $post['new_db'] : '') . '" size="50" autocomplete="off" /></td>
				<td>旧服务器数据库名:</td>
				<td><input name="old_db" type="text" value="' . (isset($post['old_db']) ? $post['old_db'] : '') . '" size="50" autocomplete="off" /></td>
			</tr>
			<tr>
				<td><input type="hidden" name="action" value="diff" /></td>
				<td colspan="2" align="center"><input type="submit" name="submit" value="提交" /></td>
				<td>&nbsp</td>
			</tr>
			<tr>
				<td colspan="4"><span class="red">注意:程序无法判断修改表名的情况，如果继续操作，会删除原表新建一张表，原来的数据全部丢失。<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;新指的是包含较新结构的数据库，一般为本地数据库，旧指的是未更新修改的数据库，一般为服务器上数据库。<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;数据库操作有风险，请审查生成的SQL语句后执行提交，发生任何问题责任自负。<br /><br />作者:傻东 <a href="http://sillydong.com" target="_blank">sillydong.com</a></span></td>
			</tr>
		</table>
	</form>
</div>';
	echo $form_html;
	getfooter();
}

function showdiff($old_host, $old_user, $old_pass, $old_db, $sqls, $errors = array(), $keys = array()) {
	getheader("对比结果");
	$table_html = '';
	foreach ($sqls as $key => $sql) {
		$table_html .= '<tr><td><input type="checkbox" name="keys[]" id="key_' . $key . '" value="' . $key . '"' . (in_array($key, $keys) ? 'checked="checked"' : '') . ' /></td><td><label for="key_' . $key . '">' . htmlentities($sql) . '</label></td></tr>';
	}
	$server_html = '<input type="hidden" name="old_host" value="' . $old_host . '" /><input type="hidden" name="old_user" value="' . $old_user . '" /><input type="hidden" name="old_pass" value="' . $old_pass . '" /><input type="hidden" name="old_db" value="' . $old_db . '"/>';
	$error_html = "<div>";
	if (!empty($errors)) {
		foreach ($errors as $error) {
			$error_html .= "<p class='red'>" . $error . "</p>";
		}
	}
	$error_html .= "</div>";
	$form_html = '
<div style="margin:20px 200px 30px 200px;">
	<div class="title">对比结果 —— MySQL_DB_Diff </div>
	' . $error_html . '
	<p>共' . count($sqls) . '条</p>
	<form method="POST">
		<table width="100%" border="0" cellpadding="5" cellspacing="0">
			' . $table_html . '
			<tr>
				<td><input type="hidden" name="action" value="write" /><input type="hidden" name="sqls" value="' . htmlentities(serialize($sqls)) . '" />' . $server_html . '</td>
				<td><input type="submit" name="submit" value="提交" onclick="return confirm(\'确认要执行指定SQL吗?\')"/></td>
			</tr>
		</table>
	</form>
</div>';
	echo $form_html;
	getfooter();
}

function compare_database($new, $old) {
	$diff = array('table' => array(), 'field' => array(), 'index' => array());
	//table
	foreach ($old['table'] as $table_name => $table_detail) {
		if (!isset($new['table'][$table_name]))
			$diff['table']['drop'][$table_name] = $table_name; //删除表
	}
	foreach ($new['table'] as $table_name => $table_detail) {
		if (!isset($old['table'][$table_name])) {
			//新建表
			$diff['table']['create'][$table_name] = $table_detail;
			$diff['field']['create'][$table_name] = $new['field'][$table_name];
			$diff['index']['create'][$table_name] = $new['index'][$table_name];
		}
		else{
			//对比表
			$old_detail=$old['table'][$table_name];
			$change=array();
			if($table_detail['Engine']!=$old_detail['Engine'])
				$change['Engine']=$table_detail['Engine'];
			if($table_detail['Row_format']!=$old_detail['Row_format'])
				$change['Row_format']=$table_detail['Row_format'];
			if($table_detail['Collation']!=$old_detail['Collation'])
				$change['Collation']=$table_detail['Collation'];
			//if($table_detail['Create_options']!=$old_detail['Create_options'])
			//	$change['Create_options']=$table_detail['Create_options'];
			if($table_detail['Comment']!=$old_detail['Comment'])
				$change['Comment']=$table_detail['Comment'];
			if(!empty($change))
				$diff['table']['change'][$table_name]=$change;
		}
	}

	//fields
	foreach ($old['field'] as $table => $fields) {
		if (isset($new['field'][$table])) {
			$new_fields = $new['field'][$table];
			foreach ($fields as $field_name => $field_detail) {
				if (!isset($new_fields[$field_name])) {
					//字段不存在，删除字段
					$diff['field']['drop'][$table][$field_name] = $field_detail;
				}
			}
		}
		else {
			//旧数据库中的表在新数据库中不存在，需要删除
		}
	}
	foreach ($new['field'] as $table => $fields) {
		if (isset($old['field'][$table])) {
			$old_fields = $old['field'][$table];
			$last_field = '';
			foreach ($fields as $field_name => $field_detail) {
				if (isset($old_fields[$field_name])) {
					//字段存在，对比内容
					if ($field_detail['Type'] != $old_fields[$field_name]['Type'] || $field_detail['Collation'] != $old_fields[$field_name]['Collation'] || $field_detail['Null'] != $old_fields[$field_name]['Null'] || $field_detail['Default'] != $old_fields[$field_name]['Default'] || $field_detail['Extra'] != $old_fields[$field_name]['Extra'] || $field_detail['Comment'] != $old_fields[$field_name]['Comment']) {
						$diff['field']['change'][$table][$field_name] = $field_detail;
					}
				}
				else {
					//字段不存在，添加字段
					$field_detail['After'] = $last_field;
					$diff['field']['add'][$table][$field_name] = $field_detail;
				}
				$last_field = $field_name;
			}
		}
		else {
			//新数据库中的表在旧数据库中不存在，需要新建
		}
	}

	//index
	foreach ($old['index'] as $table => $indexs) {
		if (isset($new['index'][$table])) {
			$new_indexs = $new['index'][$table];
			foreach ($indexs as $index_name => $index_detail) {
				if (!isset($new_indexs[$index_name])) {
					//索引不存在，删除索引
					$diff['index']['drop'][$table][$index_name] = $index_name;
				}
			}
		}
		else {
			if (!isset($diff['table']['drop'][$table])) {
				foreach ($indexs as $index_name => $index_detail) {
					$diff['index']['drop'][$table][$index_name] = $index_name;
				}
			}
		}
	}
	foreach ($new['index'] as $table => $indexs) {
		if (isset($old['index'][$table])) {
			$old_indexs = $old['index'][$table];
			foreach ($indexs as $index_name => $index_detail) {
				if (isset($old_indexs[$index_name])) {
					//存在，对比内容
					if ($index_detail['Non_unique'] != $old_indexs[$index_name]['Non_unique'] || $index_detail['Column_name'] != $old_indexs[$index_name]['Column_name'] || $index_detail['Collation'] != $old_indexs[$index_name]['Collation'] || $index_detail['Index_type'] != $old_indexs[$index_name]['Index_type']) {
						$diff['index']['drop'][$table][$index_name] = $index_name;
						$diff['index']['add'][$table][$index_name] = $index_detail;
					}
				}
				else {
					//不存在，新建索引
					$diff['index']['add'][$table][$index_name] = $index_detail;
				}
			}
		}
		else {
			if (!isset($diff['table']['create'][$table])) {
				foreach ($indexs as $index_name => $index_detail) {
					$diff['index']['add'][$table][$index_name] = $index_detail;
				}
			}
		}
	}

	return $diff;
}

function get_db_detail($server, $username, $password, $database, &$errors = array()) {
	$connection = @mysql_connect($server, $username, $password);
	if ($connection === false) {
		$errors[] = '无法连接服务器:' . $server . ':' . $username . ':' . $password . ":" . $database;
		return false;
	}
	$serverset = 'character_set_connection=utf8, character_set_results=utf8, character_set_client=binary';
	$serverset .= @mysql_get_server_info($connection) > '5.0.1' ? ', sql_mode=\'\'' : '';
	@mysql_query("SET $serverset", $connection);
	if (!@mysql_select_db($database, $connection)) {
		$errors[] = '无法使用数据库:' . $database;
		@mysql_close($connection);
		return false;
	}

	$detail = array('table' => array(), 'field' => array(), 'index' => array());
	$tables = query($connection, "show table status");
	if ($tables) {
		foreach ($tables as $key_table => $table) {
			$detail['table'][$table['Name']] = $table;
			//字段
			$fields = query($connection, "show full fields from `" . $table['Name'] . "`");
			if ($fields) {
				foreach ($fields as $key_field => $field) {
					$fields[$field['Field']] = $field;
					unset($fields[$key_field]);
				}
				$detail['field'][$table['Name']] = $fields;
			}
			else {
				$errors[] = '无法获得表的字段:' . $database . ':' . $table['Name'];
			}
			//索引
			$indexes = query($connection, "show index from `" . $table['Name'] . "`");
			if ($indexes) {
				foreach ($indexes as $key_index => $index) {
					if (!isset($indexes[$index['Key_name']])) {
						$index['Column_name'] = array($index['Seq_in_index'] => $index['Column_name']);
						$indexes[$index['Key_name']] = $index;
					}
					else
						$indexes[$index['Key_name']]['Column_name'][$index['Seq_in_index']] = $index['Column_name'];
					unset($indexes[$key_index]);
				}
				$detail['index'][$table['Name']] = $indexes;
			}
			else {
				//$errors[]='无法获得表的索引信息:'.$database.':'.$table['Name'];
				$detail['index'][$table['Name']] = array();
			}
		}
		@mysql_close($connection);
		return $detail;
	}
	else {
		$errors[] = '无法获得数据库的表详情:' . $database;
		@mysql_close($connection);
		return false;
	}
}

function update_db($server, $username, $password, $database, $sqls, &$errors = array()) {
	$connection = @mysql_connect($server, $username, $password);
	if ($connection === false) {
		$errors[] = '无法连接服务器:' . $server . ':' . $username . ':' . $password . ":" . $database;
		return false;
	}
	$serverset = 'character_set_connection=utf8, character_set_results=utf8, character_set_client=binary';
	$serverset .= @mysql_get_server_info($connection) > '5.0.1' ? ', sql_mode=\'\'' : '';
	@mysql_query("SET $serverset", $connection);
	if (!@mysql_select_db($database, $connection)) {
		$errors[] = '无法使用数据库:' . $database;
		@mysql_close($connection);
		return false;
	}
	$result = true;
	foreach ($sqls as $sql) {
		$result &= execute($connection, $sql, $errors);
	}
	return $result;
}

function query($connection, $sql) {
	if ($connection) {
		$result = @mysql_query($sql, $connection);
		if ($result) {
			$result_a = array();
			while ($row = @mysql_fetch_assoc($result))
				$result_a[] = $row;
			return $result_a;
		}
	}
	return false;
}

function execute($connection, $sql, &$errors) {
	if ($connection) {
		$result = @mysql_query($sql, $connection);
		if ($result)
			return true;
		$errors[] = @mysql_error($connection);
	}
	return false;
}

function build_query($diff) {
	$sqls = array();
	if ($diff) {
		if (isset($diff['table']['drop'])) {
			foreach ($diff['table']['drop'] as $table_name => $table_detail) {
				$sqls[] = "DROP TABLE `{$table_name}`";
			}
		}
		if (isset($diff['table']['create'])) {
			foreach ($diff['table']['create'] as $table_name => $table_detail) {
				$fields = $diff['field']['create'][$table_name];
				$sql = "CREATE TABLE `$table_name` (";
				$t = array();
				$k = array();
				foreach ($fields as $field) {
					$t[] = "`{$field['Field']}` " . strtoupper($field['Type']) . sqlnull($field['Null']) . sqldefault($field['Default']) . sqlextra($field['Extra']) . sqlcomment($field['Comment']);
				}
				if (isset($diff['index']['create'][$table_name]) && !empty($diff['index']['create'][$table_name])) {
					$indexs = $diff['index']['create'][$table_name];
					foreach ($indexs as $index_name => $index_detail) {
						if ($index_name == 'PRIMARY')
							$k[] = "PRIMARY KEY (`" . implode('`,`', $index_detail['Column_name']) . "`)";
						else
							$k[] = ($index_detail['Non_unique'] == 0 ? "UNIQUE" : "INDEX") . "`$index_name`"." (`" . implode('`,`', $index_detail['Column_name']) . "`)";
					}
				}
				list($charset) = explode('_', $table_detail['Collation']);
				$sql .= implode(', ', $t) . (!empty($k) ? ',' . implode(', ', $k) : '') . ') ENGINE = ' . $table_detail['Engine'] . ' DEFAULT CHARSET = ' . $charset;
				$sqls[] = $sql;
			}
		}
		if(isset($diff['table']['change'])){
			foreach($diff['table']['change'] as $table_name=>$table_changes){
				if(!empty($table_changes)){
					$sql="ALTER TABLE `$table_name`";
					foreach($table_changes as $option=>$value){
						if($option=='Collation'){
							list($charset) = explode('_', $value);
							$sql.=" DEFAULT CHARACTER SET $charset COLLATE $value";
						}
						else
							$sql.=" ".strtoupper($option)." = $value ";
					}
					$sqls[]=$sql;
				}
			}
		}
		if (isset($diff['field']['drop'])) {
			foreach ($diff['field']['drop'] as $table_name => $fields) {
				foreach ($fields as $field_name => $field_detail) {
					$sqls[] = "ALTER TABLE `$table_name` DROP `$field_name`";
				}
			}
		}
		if (isset($diff['field']['add'])) {
			foreach ($diff['field']['add'] as $table_name => $fields) {
				foreach ($fields as $field_name => $field_detail) {
					$sqls[] = "ALTER TABLE `$table_name` ADD `{$field_name}` " . strtoupper($field_detail['Type']) . sqlcol($field_detail['Collation']) . sqlnull($field_detail['Null']) . sqldefault($field_detail['Default']) . sqlextra($field_detail['Extra']) . sqlcomment($field_detail['Comment']) . " AFTER `{$field_detail['After']}`";
				}
			}
		}
		if (isset($diff['index']['drop'])) {
			foreach ($diff['index']['drop'] as $table_name => $indexs) {
				foreach ($indexs as $index_name => $index_detail) {
					if ($index_name == 'PRIMARY')
						$sqls[] = "ALTER TABLE `$table_name` DROP PRIMARY KEY";
					else
						$sqls[] = "ALTER TABLE `$table_name` DROP INDEX `$index_name`";
				}
			}
		}
		if (isset($diff['index']['add'])) {
			foreach ($diff['index']['add'] as $table_name => $indexs) {
				foreach ($indexs as $index_name => $index_detail) {
					if ($index_name == 'PRIMARY')
						$sqls[] = "ALTER TABLE `$table_name` ADD PRIMARY KEY (`" . implode('`,`', $index_detail['Column_name']) . "`)";
					else
						$sqls[] = "ALTER TABLE `$table_name` ADD" . ($index_detail['Non_unique'] == 0 ? " UNIQUE " : " INDEX ") . "`$index_name`" ." (`" . implode('`,`', $index_detail['Column_name']) . "`)";
				}
			}
		}
		if (isset($diff['field']['change'])) {
			foreach ($diff['field']['change'] as $table_name => $fields) {
				foreach ($fields as $field_name => $field_detail) {
					$sqls[] = "ALTER TABLE `$table_name` CHANGE `{$field_name}` `{$field_name}` " . strtoupper($field_detail['Type']) . sqlcol($field_detail['Collation']) . sqlnull($field_detail['Null']) . sqldefault($field_detail['Default']) . sqlextra($field_detail['Extra']) . sqlcomment($field_detail['Comment']);
				}
			}
		}
	}

	return $sqls;
}

function sqlkey($val) {
	switch ($val) {
		case 'PRI':
			return ' PRIMARY';
		case 'UNI':
			return ' UNIQUE';
		case 'MUL':
			return ' INDEX';
		default:
			return '';
	}
}

function sqlcol($val) {
	switch ($val) {
		case null:
			return '';
		default:
			list($charset) = explode('_', $val);
			return ' CHARACTER SET ' . $charset . ' COLLATE ' . $val;
	}
}

function sqldefault($val) {
	switch ($val) {
		case null:
			return '';
		default:
			return " DEFAULT '" . stripslashes($val) . "'";
	}
}

function sqlnull($val) {
	switch ($val) {
		case 'NO':
			return ' NOT NULL';
		case 'YES':
			return ' NULL';
		default:
			return '';
	}
}

function sqlextra($val) {
	switch ($val) {
		case '':
			return '';
		default:
			return ' ' . strtoupper($val);
	}
}

function sqlcomment($val) {
	switch ($val) {
		case '':
			return '';
		default:
			return " COMMENT '" . stripslashes($val) . "'";
	}
}

?>
