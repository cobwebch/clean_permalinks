**Description**

TYPO3 extension allowing access to pages through a pretty RealURL permalink, using the format /page/<page id>/.

**Basic principles**

The extension requires RealURL, as it uses the decodeSpURL_preProc to check the current URL. If it matches the expected permalink format, 
the URL to the target page is then generated (or fetched from RealURL cache), and a 301 redirection is applied to this URL.
