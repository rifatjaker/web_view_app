/**
 * Live preview. Must match lib/sms_segments.php.
 * GSM-7: 160, then 153. Unicode UTF-16 units: 70, then 67.
 */
(function (global) {
    const GSM_BASIC = new Set(
        ("@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#$¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà").split("")
    );
    const GSM_EXT = new Set(("\f^{}\\[~]|€").split(""));

    function partCount(length, singleLimit, multiLimit) {
        if (length <= 0) {
            return 0;
        }
        if (length <= singleLimit) {
            return 1;
        }
        return Math.ceil(length / multiLimit);
    }

    function utf16Length(message) {
        let units = 0;
        for (const char of message) {
            units += char.codePointAt(0) > 0xffff ? 2 : 1;
        }
        return units;
    }

    function smsTextStats(message) {
        if (message === "") {
            return { encoding: "gsm", length: 0, segments: 0 };
        }

        let septets = 0;
        let isGsm = true;
        for (const char of message) {
            if (GSM_BASIC.has(char)) {
                septets += 1;
            } else if (GSM_EXT.has(char)) {
                septets += 2;
            } else {
                isGsm = false;
                break;
            }
        }

        if (isGsm) {
            return {
                encoding: "gsm",
                length: septets,
                segments: partCount(septets, 160, 153)
            };
        }

        const units = utf16Length(message);
        return {
            encoding: "unicode",
            length: units,
            segments: partCount(units, 70, 67)
        };
    }

    global.smsTextStats = smsTextStats;
})(window);
