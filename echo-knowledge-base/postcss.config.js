function clamp(num, min, max) {
	return Math.max(min, Math.min(num, max));
}

const isMinify = process.env.POSTCSS_MODE === 'minified';

module.exports = {
	plugins: [
		(root) => {
			root.walkDecls((decl) => {
				let value = decl.value;

				// Perform for minified CSS files
				if (isMinify) {
					const rgbaRoundRegex = /rgba\(\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*,\s*([\d.]+)\s*\)/gi;
					value = value.replace(rgbaRoundRegex, (match, r, g, b, a) => {
						let alphaValue = parseFloat(a);
						alphaValue = Math.min(1, Math.max(0, alphaValue));
						alphaValue = parseFloat(alphaValue.toFixed(3));
						return `rgba(${r}, ${g}, ${b}, ${alphaValue})`;
					});

				// Perform for non-minified CSS files (after SCSS compiled into CSS)
				} else {

					// Convert HSLA into RGBA
					const hslaRegex = /hsla?\(\s*(-?\d+(?:\.\d+)?)(?:deg)?\s*,\s*(-?\d+(?:\.\d+)?)%\s*,\s*(-?\d+(?:\.\d+)?)%\s*(?:,\s*([\d.]+))?\)/gi;
					value = value.replace(hslaRegex, (match, h, s, l, a = 1) => {
						// Convert HSL to RGBA (naive approach):
						let hue = parseFloat(h) % 360;
						if (hue < 0) hue += 360;
						const sat = parseFloat(s) / 100;
						const lig = parseFloat(l) / 100;
						let alpha = parseFloat(a);

						alpha = parseFloat(alpha.toFixed(3));

						const c = (1 - Math.abs(2 * lig - 1)) * sat;
						const x = c * (1 - Math.abs(((hue / 60) % 2) - 1));
						const m = lig - c / 2;

						let rPrime = 0, gPrime = 0, bPrime = 0;
						if (0 <= hue && hue < 60)      { rPrime = c; gPrime = x; bPrime = 0; }
						else if (60 <= hue && hue < 120)  { rPrime = x; gPrime = c; bPrime = 0; }
						else if (120 <= hue && hue < 180) { rPrime = 0; gPrime = c; bPrime = x; }
						else if (180 <= hue && hue < 240) { rPrime = 0; gPrime = x; bPrime = c; }
						else if (240 <= hue && hue < 300) { rPrime = x; gPrime = 0; bPrime = c; }
						else if (300 <= hue && hue < 360) { rPrime = c; gPrime = 0; bPrime = x; }

						let R = Math.round((rPrime + m) * 255);
						let G = Math.round((gPrime + m) * 255);
						let B = Math.round((bPrime + m) * 255);

						// Return RGBA if alpha < 1, else plain RGB
						if (alpha < 1) {
							return `rgba(${R}, ${G}, ${B}, ${alpha})`;
						} else {
							return `rgb(${R}, ${G}, ${B})`;
						}
					});

					// Convert *simple* rgb(...) into uppercase 6-digit HEX
					const rgbRegex = /rgb\(\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\)/gi;
					value = value.replace(rgbRegex, (match, r, g, b) => {
						let R = clamp(Math.round(parseFloat(r)), 0, 255);
						let G = clamp(Math.round(parseFloat(g)), 0, 255);
						let B = clamp(Math.round(parseFloat(b)), 0, 255);
						const hexStr = [R, G, B]
							.map((c) => c.toString(16).padStart(2, '0').toUpperCase())
							.join('');
						return `#${hexStr}`;
					});

					// Convert short or lowercase HEX to uppercase 6-digit HEX
					const hexRegex = /#([\da-fA-F]{3}|[\da-fA-F]{6})(?![\da-fA-F])/g;
					value = value.replace(hexRegex, (match, hexPart) => {
						let hexStr = hexPart.toUpperCase(); // uppercase

						// Expand 3-digit hex (#ABC -> #AABBCC)
						if (hexStr.length === 3) {
							hexStr = hexStr
								.split('')
								.map((digit) => digit + digit)
								.join('');
						}
						return `#${hexStr}`;
					});
				}

				decl.value = value;
			});
		}
	],
};