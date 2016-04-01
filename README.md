Assets (New Requirements Backend)
==================================================

Features
--------
* Access using: `singleton('require')`
* You now have some sort of priority for your requirements
    - ->before
    - ->after
    - ->first
    - ->last
* Defer CSS and javascript until page loads using: ->defer
* Inline CSS and javascript using: ->inline
* Replace certain files using: ->replace
* Insert CSS and javascript into the <head> using: ->head
* New globals for your templates
    - $inlineFile(''): Inline a file at this section of a template
    - $placeIMG($width = 400, $height = 300, $categories = 'any', $filters = ''): Insert a placeholder image courtesy of placeimg.com
    - $loremIpsum($paragraphs = 1, $length = 'short', $opts = ['plaintext']): Insert lorem ipsum text courtesy of loripsum.net

## Install
Add the following to your composer.json file

```

    "require"          : {
		"milkyway-multimedia/ss-mwm-assets": "dev-master"
	}

```

## License
* MIT

## Version
* Version 0.3 (Alpha)

## Contact
#### Mellisa Hankins
* E-mail: mellisa.hankins@me.com
* Twitter: [@mi3ll](https://twitter.com/mi3ll "mi3ll on twitter")
* Website: mellimade.com.au
