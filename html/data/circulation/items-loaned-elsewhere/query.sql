SELECT TITLE, AUTHOR, IMPRINT, YEAR, ISN, SHORT_RECORDS.BIB_NUMBER AS BIB_NUMBER,
       SUB_LIBRARY, COLLECTION, CALLNUMBER, DESCRIPTION, BARCODE, ITEM_STATUS, PROCESS_STATUS, MATERIAL_TYPE,
       Z35_EVENT_DATE AS LOAN_DATE, Z35_IP_ADDRESS AS LOAN_LOCATION
FROM NOV50.Z35
JOIN WEBREPORT.ITEM_RECORDS ON Z30_REC_KEY = Z35_REC_KEY || LPAD(Z35_ITEM_SEQUENCE,6,'0')
JOIN WEBREPORT.ADM_BIB_LOOKUP ON ITEM_RECORDS.ADM_NUMBER = ADM_BIB_LOOKUP.ADM_NUMBER
JOIN WEBREPORT.SHORT_RECORDS ON SHORT_RECORDS.BIB_NUMBER = ADM_BIB_LOOKUP.BIB_NUMBER
WHERE Z35_EVENT_TYPE = '50'
AND Z35_UPD_TIME_STAMP >= :STARTDATE
AND RTRIM(Z35_SUB_LIBRARY) = :SUBLIBRARY
AND Z35_IP_ADDRESS != :CIRCDESK
