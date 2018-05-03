/*
SELECT
  Z13_TITLE AS TITLE,
  Z13_AUTHOR AS AUTHOR,
  Z13_ISBN_ISSN AS ISN,
  Z13_REC_KEY AS BIB_NUMBER,
  TRIM(REGEXP_REPLACE(Z30_CALL_NO, '\$\$[a-z0-9]', ' ')) AS CALLNUMBER,
  Z30_DESCRIPTION AS DESCRIPTION,
  TRIM(Z30_COLLECTION) AS COLLECTION,
  RTRIM(Z30_BARCODE) AS BARCODE,
  Z30_ITEM_STATUS AS ITEM_STATUS,
  Z30_ITEM_PROCESS_STATUS AS PROCESS_STATUS,
  Z36_DUE_DATE AS DUEDATE,
  Z30_UPDATE_DATE AS LAST_EDIT
FROM NOV50.Z30
   JOIN NOV50.Z103 ON 'NOV50' || SUBSTR(Z30_REC_KEY, 1, 9) = SUBSTR(Z103_REC_KEY, 1, 14)
   JOIN NOV01.Z13  ON 'NOV01' || Z13_REC_KEY = Z103_REC_KEY_1
   LEFT OUTER JOIN NOV50.Z36 ON Z30_REC_KEY = Z36_REC_KEY
WHERE RTRIM(Z30_SUB_LIBRARY) = :SUBLIB
  AND Z30_COLLECTION IN :COLLECTIONS
  AND ROWNUM < 50000
*/

SELECT
  TITLE, AUTHOR, ISN, BIB_NUMBER,
  CALLNUMBER, DESCRIPTION, COLLECTION,
  BARCODE, ITEM_STATUS, PROCESS_STATUS,
  Z36_DUE_DATE AS DUEDATE
FROM WEBREPORT.ITEM_RECORDS
NATURAL JOIN WEBREPORT.ADM_BIB_LOOKUP
NATURAL JOIN WEBREPORT.SHORT_RECORDS
LEFT OUTER JOIN NOV50.Z36 ON Z30_REC_KEY = Z36_REC_KEY
WHERE SUB_LIBRARY = :SUBLIB
  AND COLLECTION IN ( :COLLECTIONS )
  AND ROWNUM < 50000
