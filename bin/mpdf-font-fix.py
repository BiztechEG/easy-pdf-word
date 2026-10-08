#!/usr/bin/env python3
"""
Make a TrueType font usable by mPDF.

mPDF rejects two things that many recent fonts (Cairo, Noto Naskh Arabic,
most Google fonts) contain:

1. "This font contains MarkGlyphSets - Not tested yet": GDEF version 1.2.
   The mark glyph sets are removed, the lookup flag that points at them is
   cleared and GDEF goes back to version 1.0. Shaping and mark positioning
   keep working; only the optional mark filtering is lost.
2. "GPOS Lookup Type 5, Format 3 not supported": a contextual substitution
   (GSUB type 5, format 3). It is rewritten as the equivalent chained
   contextual substitution (type 6, format 3) with empty backtrack and
   lookahead, which mPDF supports. The output glyphs are identical.

Usage: python3 bin/mpdf-font-fix.py Font-Regular.ttf [Font-Bold.ttf ...]
Needs fontTools: python3 -m pip install fonttools
"""
import sys

from fontTools.ttLib import TTFont
from fontTools.ttLib.tables import otTables

USE_MARK_FILTERING_SET = 0x0010


def context_to_chain(subtable):
    chain = otTables.ChainContextSubst()
    chain.Format = 3
    chain.BacktrackGlyphCount = 0
    chain.BacktrackCoverage = []
    chain.InputGlyphCount = subtable.GlyphCount
    chain.InputCoverage = subtable.Coverage
    chain.LookAheadGlyphCount = 0
    chain.LookAheadCoverage = []
    chain.SubstCount = subtable.SubstCount
    chain.SubstLookupRecord = subtable.SubstLookupRecord
    return chain


def convert_context_lookups(font):
    changed = False
    if "GSUB" not in font or font["GSUB"].table.LookupList is None:
        return changed
    for lookup in font["GSUB"].table.LookupList.Lookup:
        if lookup.LookupType == 5 and all(st.Format == 3 for st in lookup.SubTable):
            lookup.SubTable = [context_to_chain(st) for st in lookup.SubTable]
            lookup.LookupType = 6
            changed = True
        elif lookup.LookupType == 7:
            for st in lookup.SubTable:
                if st.ExtensionLookupType == 5 and st.ExtSubTable.Format == 3:
                    st.ExtSubTable = context_to_chain(st.ExtSubTable)
                    st.ExtensionLookupType = 6
                    changed = True
    return changed


def remove_mark_glyph_sets(font):
    gdef = font["GDEF"].table if "GDEF" in font else None

    if gdef is None or getattr(gdef, "MarkGlyphSetsDef", None) is None:
        return False

    for tag in ("GSUB", "GPOS"):
        if tag not in font or font[tag].table.LookupList is None:
            continue
        for lookup in font[tag].table.LookupList.Lookup:
            if lookup.LookupFlag & USE_MARK_FILTERING_SET:
                lookup.LookupFlag &= ~USE_MARK_FILTERING_SET
                lookup.MarkFilteringSet = None

    gdef.MarkGlyphSetsDef = None
    gdef.Version = 0x00010000
    return True


def fix(path):
    font = TTFont(path)
    changed = remove_mark_glyph_sets(font)
    changed = convert_context_lookups(font) or changed

    if changed:
        font.save(path)
        print(f"{path}: fixed")
    else:
        print(f"{path}: nothing to change")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    for file in sys.argv[1:]:
        fix(file)
