# Sync Google Apps Script

This app synchronizes a Google Apps Script project in Google Drive, with a local copy.   
Copy files from **Local &rarr; Google Drive** or **Google Drive &rarr; Local**. 

Destination Google Drive files with a .gs or .html extension are overwritten.  
Destination Local files with a .gs, .js or .html extension are overwritten. All other files & folders remain unchanged.

* Requires the [Google APIs Client Libary for PHP](https://github.com/google/google-api-php-client)  
* Requires a [Google Service account](https://support.google.com/a/answer/7378726?hl=en)  

This app works with **stand-alone** sripts and **container-bound** scripts.

Local folders may include a manifest.json file in their root, that contains the script ID for the script the folder is to be syncronized with:

`{"scriptId":"19hP7JINrr85jQs-hSjhbMPmyniXadDqrwKKfl7PJEjbkyJhFJ0UKi_IS"}`

Otherwise, enter the script ID into the **_Destination Script ID_** box.

The script ID may be found by opening the script in the google script editor, then **_file > Project properties > info tab_**.
