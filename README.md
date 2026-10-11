# Introduction

"Stopulator" is a tool for collating lists of organ stops used on virtual pipe organs. It is intended to solve the problem of how to determine what the minimum set of discrete stopheads is for a given set of virtual organs, so that just the minimum set of stopheads can be produced for a given set of sample sets for use on the virtual organ.

Its features, in broad summary, are:

- Each organ defintion has a short name (e.g. "stanne") and a long name (e.g. "St. Anne's, Moseley")
- Each organ definition is contained in a single file containing one line per stop or division, allowing for comments
- Each organ can have as many divisions or jamb labels as you wish (e.g. "GREAT", "PEDAL", "ACCESSORIES")
- By default, stopheads are coloured black.
- Stopulator will spot certain words like "on" and "to" and automatically pick Dark Red as a colour, for couplers etc.
- Similarly, where words such as "Anches" are used (e.g. for Cavaille Colle ventils on stopheads) it will switch to Dark Blue
- It is possible to force the colour of a particular stop with a character prefix on the line containing the stop
- The Stopulator is provided as a PHP library which interacts with a MariaDB/MySQL database (schema provided). The intent is that this could be deployed into a web environment if desired.
- A sample command line utility which simply uses the library is provided. This uses a configuration file to provide the database login parameters.
- A function in the library (and an option on the command line utility) then allows the production of a list of stopheads which is the minimum required for a given set of organs, identified by short name

The basic functions of the library are:

- Store a new organ definition
- Delete an organ definition
- List known organs (shortnames and full names)
- List minimum set of stopheads (with colours and quantity) for a given set of organs

# More to come

Proper documentation for the library to follow, but for now have a look at the CLI sample - `stopulator.php`


