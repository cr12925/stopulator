<?php

/*
    (c) 2026 Chris Royle
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.

*/

function stopulator_insert_one ($mysqli, $table, $index_key, $value_key, $value)
{
	$id = 0;
	$my_id = 0;

	$stmt = $mysqli->prepare("SELECT ".$index_key." FROM ".$table." WHERE ".$value_key."=?");
	if ($stmt === false)
	{
		printf("Cannot prepare $table select statement for $value\n");
		exit();
	}
	
	$stmt->bind_param("s", $value);
	$result = $stmt->execute();

	if (!$result)
	{
		printf("Cannot execute $table select statement for $value\n");
		exit();
	}
	
	$stmt->bind_result($my_id);
	$stmt->fetch();

	$id = $my_id;

	$stmt->free_result();
	
	if (!$id)
	{
		$stmt = $mysqli->prepare("INSERT INTO $table VALUES(0,?)");
		if ($stmt === false)
		{
			printf("Cannot prepare $table insert statement for $value\n");
			exit();
		}
		$stmt->bind_param("s", $value);
		if (!$stmt->execute())
		{
			printf("Cannot execute $table insert statement for $value\n");
			exit();
		}
		
		$id = $mysqli->insert_id;
	}

	if (!$id)
	{
		printf("Something went wrong getting ID inserting $division $value\n");
		exit();
	}

	return $id;

}

function stopulator_connect(&$mysqli, $server, $db, $user, $pw)
{
	if (!$mysqli->real_connect($server, $db, $user, $pw))
		return NULL;

	return $mysqli;
}

function stopulator_findorgan($mysqli, $organ_shortname)
{

	$organname = strtolower($organ_shortname);

	$stmt = $mysqli->prepare("select organ_id from organ where organ_shortname=?");
	$stmt->bind_param("s", $organname);
	unset($myresult);
	$stmt->execute();
	$stmt->bind_result($myresult);
	$stmt->fetch();	
	if ($myresult)
		$stmt->free_result();

	return $myresult;
}

function stopulator_deleteorgan($mysqli, $organname)
{
	$organ_id = stopulator_findorgan($mysqli, $organname);

	if (!$organ_id)
		return NULL; /* Not found */
	else
	{
		/* Delete from organ, and then organ_stoplist */

		$stmt = $mysqli->prepare("delete from organ where organ_id = ?");
		$stmt->bind_param("i", $organ_id);
		$stmt->execute();

		$stmt = $mysqli->prepare("delete from organ_stoplist where organ_id = ?");
		$stmt->bind_param("i", $organ_id);
		$stmt->execute();

		return $organ_id; /* The deleted organ ID */
	}
}

function stopulator_createorgan($mysqli, $organ_name, $organ_shortname)
{
	
	$stmt = $mysqli->prepare("insert into organ(organ_name, organ_shortname) values (?,?)");
	$stmt->bind_param("ss", $organ_name, $organ_shortname);
	$stmt->execute();
	$organ_id = $mysqli->insert_id;

	return $organ_id;
}

function stopulator_import_stops($mysqli, $organ_id, $lines)
{
	$division = "";
	
	$output = array();
	$divisions = array();
	$bases = array();
	$colours = array();
	
	foreach ($lines as $line)
	{
		if (preg_match("/^\#(.+)$/", $line, $matches))
		{
			$division = $matches[1];
			continue;
		}
		else
		{
			$force_red = $force_black = $force_blue = 0;
	
			if (preg_match ("/^\./", $line, $inner_matches))
				continue;
			else if (preg_match ("/^\+(.+)$/", $line, $inner_matches))
			{
				# Force red if first character is +
				$line = $inner_matches[1];
				$force_red = 1;
			}
			else if (preg_match ("/^\-(.+)$/", $line, $inner_matches))
			{
				# Force black if first character is -
				$line = $inner_matches[1];
				$force_black = 1;
			}
			else if (preg_match ("/^\\*(.+)$/", $line, $inner_matches))
			{
				# Force black if first character is -
				$line = $inner_matches[1];
				$force_blue = 1;
			}
	
	
			if (preg_match("/\b(À|on|to)\b/i", $line))
			{
				$base = $line;
				$colour = "Dark Red";
				$length = "";
			}
			else if (preg_match("/(Octave|Sub Octave|Unison Off|Tremulant)\s*$/i", $line))
			{
				$base = $line;
				$colour = "Dark Red";
				$length = "";
			}
			else if (preg_match("/\s*(Orage|Anches)/i", $line))
			{
				$base = $line;
				$colour = "Dark Blue";
				$length = "";
			}
			else
			{
				$base = $line;
				$colour = "Black";
			}
	
			if ($force_red)
				$colour = "Dark Red";
	
			if ($force_black)
				$colour = "Black";
	
			if ($force_blue)
				$colour = "Dark Blue";
	
			$output[] = array($division, $base, $colour);
			
			$divisions[$division] = stopulator_insert_one($mysqli, "division", "division_id", "division_name", $division);
	
			// printf ("Line: '%s'; base '%s', length '%s', colour '%s'\n", $line, $base, $length, $colour);
	
			$bases[$base] = stopulator_insert_one($mysqli, "stopname", "stopname_id", "stopname", $base);
	
			$colours[$colour] = stopulator_insert_one($mysqli, "colour", "colour_id", "colour_name", $colour);
	
			unset($myresult);
	
			$stmt = $mysqli->prepare("select organstop_id from organstop where stopname_id=? and colour_id=?");
			$stmt->bind_param("ii", $bases[$base], $colours[$colour]);
			$stmt->execute();
			$stmt->bind_result($myresult);
			$stmt->fetch();
	
			if (!$myresult)
			{
				$stmt = $mysqli->prepare("insert into organstop(stopname_id, colour_id) values (?, ?)");
				if (!$stmt)
				{
					return NULL;
					//printf ("Error preparing insert into organstop!\n");
					//exit();
				}
	
				$stmt->bind_param("ii", $bases[$base], $colours[$colour]);
				if (!($stmt->execute()))
				{
					return NULL;
					//printf ("Error executing insert into organstop!\n");
					//exit();
				}
				$myresult = $mysqli->insert_id;
			}
			else $stmt->free_result();
	
			$stmt = $mysqli->prepare("replace into organ_stoplist(organ_id, division_id, organstop_id) values (?, ?, ?)");
			if (!$stmt)
			{
				return NULL;
				//printf ("Error preparing insert into organ_stoplist!\n");
				//exit();
			}
			$stmt->bind_param("iii", $organ_id, $divisions[$division], $myresult);
			if (!$stmt->execute())
			{
				return NULL;
				//printf ("Error executing insert into organ_stoplist for $organ_id, $divisions[$division], $my_result\n");
				//exit();
			}
	
		}
	}

	return 1;
}

function stopulator_csvout($lines, $file)
{
	$outputfile = fopen($file, "w");

	foreach ($lines as $line)
	{
		$output_line = implode(',',$line)."\n";
		fwrite($outputfile, $output_line);
	}

	fclose ($outputfile);
}

function stopulator_close(&$mysqli)
{
	$mysqli->close();
}

function stopulator_collate($mysqli, $organs)
{

	$output = array();

	if (count($organs) < 1 || !is_array($organs))
		$organ_list = "";
	else
	{

		$stoplist = array();
	
		foreach ($organs as $key => $organ)
			$organs[$key] = strtolower($organ);
	
		$organ_list = "AND organ_shortname IN ('".implode("','", $organs)."') ";
	}

	$stmt = $mysqli->query("drop table if exists stopulator_t1");
	if (!$stmt)
		return NULL;

	$myresult = $mysqli->query("
	create temporary table stopulator_t1 
	select
		organ_name,
		organstop.organstop_id,  
		stopname as stop,
		colour_name as colour,
		count(organ_stoplist.os_id) as number
	from
		organ_stoplist, organstop, stopname, colour, organ
	where
		organ_stoplist.organ_id = organ.organ_id
	and	organ_stoplist.organstop_id = organstop.organstop_id
	and	organstop.stopname_id = stopname.stopname_id
	and	organstop.colour_id = colour.colour_id
	$organ_list
	group by
		organ_stoplist.organ_id, organstop.organstop_id;
	");

	if (!$myresult)
		return NULL;

	/* t1 should now be created; now sift into t1 */

	$myresult = $mysqli->query("
	select
		stop,
		colour,
		max(number) as number
	from stopulator_t1
	group by	organstop_id
	order by	stop asc
	");

	if (!$myresult)
		return NULL;

	while ($r = $myresult->fetch_array())
	{ $output[] = $r; }

	$myresult->free_result();

	return $output;
}

function stopulator_get_organs($mysqli)
{
	$result = array();

	$myresult = $mysqli->query("select organ_name, organ_shortname from organ order by organ_shortname asc");
	if (!$myresult)
		return NULL;
	while ($r = $myresult->fetch_assoc()) 
		$result[$r['organ_shortname']] = $r['organ_name'];

	$myresult->free_result();

	return $result;
}

?>
