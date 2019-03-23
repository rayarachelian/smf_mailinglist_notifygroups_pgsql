These are two SMF mods that I wish to use, but my SMF 2.0.15 uses postgress 11 as a backend.

So unfortunately INSERT IGNORE generates errors, but replacing these with INSERT ... ON CONFLICT DO NOTHING in the sql statements these mods use fixes the issue.

Putting this here incase anyone else runs into the same issue.

