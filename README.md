# Sync Google Apps Script

This app synchronizes a Google Apps Script project in Google Drive, with a local copy.   
Copy files from **Local &rarr; Google Drive** or **Google Drive &rarr; Local**. 

Destination Google Drive files with a .gs or .html extension are overwritten.  
Destination Local files with a .gs/.js or .html extension are overwritten. All other files & folders remain unchanged.

* Requires the [Google APIs Client Libary for PHP](https://github.com/google/google-api-php-client)  
* Requires a [Google Service account](https://support.google.com/a/answer/7378726?hl=en)  

Note:  *This app only works with **stand-alone** sripts.  The Google Drive API does not support import/export from container-bound scripts.*
