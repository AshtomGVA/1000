# 1000 Light Years
A clone of 1000 Bornes card game in a browser using Javascript (and Knockout) for the frontend and PHP for the backend.

Installation:

- Create a database and import the structure using 1000ly.sql

- Upload the content of the 'back' folder after editing the database connection settings in db_params.php

- Upload the content of the 'front' folder after editing the thisGame.baseURL (line 3) and self.baseURL (line 963) variable in js/1000ly.js to match with the URL of the host PHP files.