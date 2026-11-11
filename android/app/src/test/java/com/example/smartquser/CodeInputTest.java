package com.example.smartquser;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertNull;
import static org.junit.Assert.assertTrue;

import com.example.smartquser.auth.CodeInput;

import org.junit.Test;

/** Typing / pasting the 6-digit email code and the resend countdown. */
public class CodeInputTest {

    @Test
    public void digitsOnlyKeepsDigitsAndCaps() {
        assertEquals("048608", CodeInput.digitsOnly("048 608", 6));
        assertEquals("048608", CodeInput.digitsOnly("0-4-8-6-0-8-9", 6));
        assertEquals("12", CodeInput.digitsOnly("a1b2", 6));
        assertEquals("", CodeInput.digitsOnly(null, 6));
        assertEquals("", CodeInput.digitsOnly("abc", 6));
    }

    @Test
    public void extractsCodeFromPastedText() {
        assertEquals("048608", CodeInput.extractCode("048608"));
        assertEquals("048608", CodeInput.extractCode("  048608\n"));
        assertEquals("048608", CodeInput.extractCode("Your SmartQ code: 048608"));
        assertEquals("048608", CodeInput.extractCode("Use 048 608 to sign in"));
        assertEquals("048608", CodeInput.extractCode("048-608"));
        assertEquals("123456", CodeInput.extractCode("Code 123456. It expires in 10 minutes."));
    }

    @Test
    public void rejectsTextWithoutAClearCode() {
        assertNull(CodeInput.extractCode(null));
        assertNull(CodeInput.extractCode(""));
        assertNull(CodeInput.extractCode("hello"));
        assertNull(CodeInput.extractCode("12345"));
        assertNull(CodeInput.extractCode("1234567"));
        assertNull(CodeInput.extractCode("Call 0791234567 today"));
    }

    @Test
    public void completeness() {
        assertTrue(CodeInput.isComplete("000000"));
        assertFalse(CodeInput.isComplete("00000"));
        assertFalse(CodeInput.isComplete("00000a"));
        assertFalse(CodeInput.isComplete(null));
    }

    @Test
    public void countdown() {
        assertEquals("1:00", CodeInput.formatCountdown(60));
        assertEquals("0:09", CodeInput.formatCountdown(9));
        assertEquals("0:00", CodeInput.formatCountdown(-4));
        assertEquals(60, CodeInput.secondsLeft(1_000, 61_000));
        assertEquals(1, CodeInput.secondsLeft(1_000, 1_001)); // rounds up: never shows 0:00 too early
        assertEquals(0, CodeInput.secondsLeft(5_000, 1_000));
    }
}
