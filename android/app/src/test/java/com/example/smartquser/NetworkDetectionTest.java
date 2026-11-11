package com.example.smartquser;

import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import com.example.smartquser.net.BotChallengeSolver;

import org.junit.Test;

/** The InfinityFree anti-bot page must be detected; normal JSON / error pages must not. */
public class NetworkDetectionTest {

    private static final String INFINITYFREE_PAGE =
            "<html><body><script type=\"text/javascript\" src=\"/aes.js\" ></script>"
            + "<script>function toNumbers(d){var e=[];d.replace(/(..)/g,function(d){e.push(parseInt(d,16))});return e}"
            + "var a=toNumbers(\"f655ba9d09a112d4968c63579db590b4\"),b=toNumbers(\"98344c2eee86c3994890592585b49f80\"),"
            + "c=toNumbers(\"1b4ad3a1b1c2e5e8f8b4a5d1c2e3f4a5\");document.cookie=\"__test=\"+toHex(slowAES.decrypt(c,2,a,b))"
            + "+\"; expires=Thu, 31-Dec-37 23:55:55 GMT; path=/\"; location.href=\"https://example.rf.gd/api/health.php?i=1\";"
            + "</script><noscript>This site requires Javascript to work, please enable Javascript in your browser"
            + " or use a browser with Javascript support</noscript></body></html>";

    @Test
    public void detectsInfinityFreeChallenge() {
        assertTrue(BotChallengeSolver.looksLikeChallenge(INFINITYFREE_PAGE));
    }

    @Test
    public void ignoresJson() {
        assertFalse(BotChallengeSolver.looksLikeChallenge("{\"status\":true,\"message\":\"ok\",\"data\":{}}"));
        assertFalse(BotChallengeSolver.looksLikeChallenge("  {\"status\":false,\"message\":\"__test\"}"));
    }

    @Test
    public void ignoresOrdinaryHtmlErrors() {
        assertFalse(BotChallengeSolver.looksLikeChallenge("<html><body><h1>404 Not Found</h1></body></html>"));
        assertFalse(BotChallengeSolver.looksLikeChallenge(""));
        assertFalse(BotChallengeSolver.looksLikeChallenge(null));
    }
}
