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

include "libstopulator.php";
include "stopulator.conf";

function stopulator_pad($str, $l)
{
	/* We really want mb_strlen to handle the multibyte characters, but we don't have it by default */
	if (is_callable('mb_strlen'))
		$mbl = mb_strlen($str);
	else	$mbl = strlen($str);
	$r = $str.str_repeat(" ", $l - $mbl);
	return $r;
}

function stopulator_help($argv)
{
	printf($argv[0].": 

-a <organ short name> \"<organ full name>\" <file>		Add / Replace organ definition
-d <organ short name> 					Delete organ definition
-l [<organ ...>]					Produce minimum stoplist for combination of organs, or all if no organ list given
-o							List known organs
");

}

$mysqli = mysqli_init();

if (!stopulator_connect($mysqli, $stopulator_server, $stopulator_db, $stopulator_user, $stopulator_pw))
{
	printf ("Unable to connect to database!\n");
	exit(EXIT_FAILURE);
}

/* Parse parameters */

if ($argv[1] == "-a") /* Add */
{

	if ($argc < 4)
	{
		stopulator_help();
	}
	else
	{
		$organ_shortname = $argv[2];
		$organ_longname = $argv[3];
		$organ_file = $argv[4];

		printf ("Adding organ '%s' (%s) from file '%s'\n", $organ_shortname, $organ_longname, $organ_file);

		stopulator_deleteorgan($mysqli, $organ_shortname);
		$organ_id = stopulator_createorgan($mysqli, $organ_longname, $organ_shortname);

		if (!$organ_id)
		{
			printf("Unable to create organ!\n");
		}
		else
		{
			$organ_array = file($organ_file);

			if (!$organ_array)
			{
				printf("Unable to read %s\n", $organ_file);
			}
			else
			{
				foreach ($organ_array as $k => $v)
					$organ_array[$k] = rtrim($v, "\n");

				$r = stopulator_import_stops($mysqli, $organ_id, $organ_array);

				if (!$r)
					printf ("Failed to import %s\n", $organ_file);
				else	printf ("Success.\n");
			}
		}
	}

}
else if ($argv[1] == "-d") /* Delete */
{
	if ($argc < 2)
		stopulator_help();
	else
	{
		$organ = strtolower($argv[2]);

		$res = stopulator_deleteorgan($mysqli, $organ);

		if (!$res)
			printf("Organ %s not found!\n", $organ);
		else	printf("Deleted.\n");
	}
	
}
else if ($argv[1] == "-l") /* List */
{
	$organs = array();

	$count = 2;

	if ($argv[2] == "-c")
	{
		$csv_out = $argv[3];
		$count = 4;
	}

	while ($count < $argc)
	{
		$organs[] = strtolower($argv[$count]);
		$count++;
	}

	$stoplist = stopulator_collate($mysqli, $organs);

	foreach ($stoplist as $k => $v)
	{
		foreach ($v as $k1 => $v1)
		{
			if (is_numeric($k1)) /* Ditch numeric indices */
				unset($stoplist[$k][$k1]);
		}
	}

	$stophead_count = 0;

	if (isset($csv_out))
		stopulator_csvout($stoplist, $csv_out);
	else
	{
		foreach ($stoplist as $stop)
		{
			printf("%s %-10s %d\n",
				stopulator_pad($stop["stop"],40), $stop["colour"], $stop["number"]);
			$stophead_count += $stop["number"];
		}
	}

	printf ("%d stopheads\n", $stophead_count);
	
}
else if ($argv[1] == "-o") /* List organs */
{
	$organs = stopulator_get_organs($mysqli);

	$maxlength = 0;

	if (!is_array($organs))
		printf("Unable to get organ list!\n");
	else
	{
		foreach ($organs as $organ => $longname)
			printf("%s\t%s\n", stopulator_pad($organ,15), $longname);	
	}

}
else
{
	stopulator_help($argv);
	if ($argv[1] != "-h")
	{
		stopulator_close($mysqli);
		exit(EXIT_FAILURE);
	}
}

stopulator_close($mysqli);

?>
