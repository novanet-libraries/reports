SELECT Z304_EMAIL_ADDRESS AS EMAIL, Z305_BOR_STATUS AS BOR_STATUS
FROM NOV50.Z303
JOIN NOV50.Z305 ON Z303_REC_KEY || Z303_HOME_LIBRARY = Z305_REC_KEY
JOIN NOV50.Z304 ON SUBSTR(Z304_REC_KEY,1,12) = Z303_REC_KEY AND Z304_ADDRESS_TYPE = 2
WHERE RTRIM(Z303_HOME_LIBRARY) = :SUBLIBRARY
AND Z305_EXPIRY_DATE > :TODAY
AND Z304_EMAIL_ADDRESS LIKE '%@%'
