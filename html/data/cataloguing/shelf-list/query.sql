SELECT
  TITLE, AUTHOR, IMPRINT, YEAR, ISN, BIB_NUMBER,
  COLLECTION, CALLNUMBER, DESCRIPTION, MATERIAL_TYPE,
  BARCODE, ITEM_STATUS, PROCESS_STATUS,
  Z36_DUE_DATE AS DUEDATE
FROM WEBREPORT.ITEM_RECORDS
NATURAL JOIN WEBREPORT.ADM_BIB_LOOKUP
NATURAL JOIN WEBREPORT.SHORT_RECORDS
LEFT OUTER JOIN NOV50.Z36 ON Z30_REC_KEY = Z36_REC_KEY
WHERE SUB_LIBRARY = :SUBLIB
  AND COLLECTION IN ( :COLLECTIONS )
  AND (CALLNUMBER IS NULL
    OR (UPPER(CALLNUMBER) >= :CNSTART AND UPPER(CALLNUMBER) <= :CNEND)
  )
  AND ROWNUM <= 50000
