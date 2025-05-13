# csv-xlsx-filetracker-api

### Tecnologias e estrutura do projeto
#### Tecnologias
Linguagem: PHP
Framework: Laravel
Banco de dados: MongoDB
Extra: Docker, Git

#### Estrutura do projeto 
O principal desafio para este projeto foi conseguir realizar o adequado tratamento das informações advindas dos arquivos CSV/XLSX, buscando garantir 100% de funcionalidade em planilhas com centenas de milhares de linhas de dados.

De início, ao tentar salvar um arquivo CSV com cerca de 300 mil linhas, acabei esbarrando na limitação do MongoDB de 16mb por documento.

A solução adotada para tal problema foi modelar os dados salvos no banco para conseguir, de forma adequada, separar o enorme arquivo CSV/XLSX em arquivos menores e garantir a sua boa eficiência para utilização dos dados em consultas ao banco.

Para isso, adotei um modelo de relacionamento entre os documentos, onde, para cada arquivo CSV/XSLX que fosse subido no sistema, fosse criado um *documento pai (type: root)*, que possui diversos *documentos filhos (type: chunk)*. Dessa forma, ao invés de tentar salvar um grande documento de mais de 16mb, o arquivo é separado em documentos de até 10 mil linhas de dados. 

Assim, os documentos filhos, *chunks* (representado pelo campo 'type': 'chunk'), possuem uma relação de vários para um com o documento raiz ('type': 'root'), expressa no campo 'root_id' dos documentos com tipo 'chunk'. Dessa forma, foi possível garantir boa legibilidade dos dados nas consultas. Também tornou possível realizar consultas buscando apenas os documentos pai, sem precisar buscar as informações de todos os documentos filhos.

### Endpoints 
A API contém 3 endpoints:
#### *POST api/files:* 
Rota para upload de arquivos. Aceita uma requisição POST com um form-data com chave "file" e um arquivo CSV/XLSX como valor.

Tipo de requisição: multipart/form-data

Campo esperado: file

**Requisição**
![image](https://github.com/user-attachments/assets/ba7108d9-fbaa-419e-a78a-306343a3fe44)

**Retorno**
```json
{
    "message": "File uploaded and chunked successfully.",
    "content_hash": "40e59c163b2720107fc718e66835f8df",
    "uploaded_metadata": {
        "root_filename": "InstrumentsConsolidatedFile_20240822_20240827.csv",
        "chunks_filename": "2025-05-13_121748_[index]_InstrumentsConsolidatedFile_20240822_20240827.csv",
        "upload_date_brasilia_local_time": "2025-05-13T09:17:48-03:00",
        "uploaded_at_utc": "2025-05-13T12:17:48.671001Z",
        "file_size": 67912434,
        "extension": "csv"
    },
    "processing_info": {
        "chunk_total_lines": 301039,
        "total_chunks": 31,
        "total_lines": 301039,
        "total_processing_time_ms": 11399
    },
    "status": 201,
    "success": true
}
```
E caso você tente subir um arquivo duplicado:
```json
{
    "error": "Duplicated file",
    "message": "A file with this content already exists in the database.",
    "duplicated_file": {
        "filename": "InstrumentsConsolidatedFile_20240822_20240827.csv",
        "content_hash": "40e59c163b2720107fc718e66835f8df",
        "upload_date_brasilia_local_time": "2025-05-13T09:17:48-03:00",
        "uploaded_metadata": {
            "original_file_size": 67912434,
            "original_extension": "csv"
        },
        "processing_info": {
            "number_of_chunks": 31,
            "chunk_total_lines": 301039
        },
        "type": "root",
        "updated_at": "2025-05-13T12:17:59.968000Z",
        "created_at": "2025-05-13T12:17:48.704000Z",
        "id": "0196c994-68a0-72cc-ad00-95060e7727ed"
    },
    "success": false
}
```

---

#### *GET api/files*:
Rota para consulta de histórico de arquivos enviados para o sistema.

Aceita os parâmetros para busca por filename ou reference date.

##### Parâmetros:

| Parâmetro             | Retorno                                                                                                | Exemplo                                     |
| --------------------- | ------------------------------------------------------------------------------------------------------ | ------------------------------------------- |
| filename              | O exato arquivo pesquisado caso ele exista no banco de dados ou uma mensagem de arquivo não encontrado | /api/files?filename=arquivo_exemplo.csv     |
| referenceDateBrasilia | Todos os arquivos do dia em questão, usando como base o campo "reference_brasilia_upload_date"         | /api/files?referenceDateBrasilia=2025-05-12 |
| referenceDateUtc      | Todos os arquivos do dia em questão, usando como base o campo "created_at"                             | /api/files?referenceDateUtx=2025-05-12      |

**Requisição**
![image](https://github.com/user-attachments/assets/786a3827-a459-48d1-822c-c4caa6fdb6f0)

**Retorno**
```json
{
    "message": "File upload history query completed successfully",
    "data": [
        {
            "filename": "arquivo_teste_2.csv",
            "content_hash": "ec2e634010146eefb176e0fa61d7018a",
            "upload_date_brasilia_local_time": "2025-05-13T09:23:17-03:00",
            "type": "root",
            "updated_at": "2025-05-13T12:23:23.957000Z",
            "created_at": "2025-05-13T12:23:17.582000Z",
            "id": "0196c999-6d4e-7182-8c58-637ba04ba8b8"
        },
        {
            "filename": "arquivo_teste.csv",
            "content_hash": "f8f02b928d514303c45f141d9f6a6a0b",
            "upload_date_brasilia_local_time": "2025-05-13T09:23:03-03:00",
            "type": "root",
            "updated_at": "2025-05-13T12:23:10.198000Z",
            "created_at": "2025-05-13T12:23:03.796000Z",
            "id": "0196c999-3774-7175-97bd-4e5be0aa919e"
        },
        {
            "filename": "InstrumentsConsolidatedFile_20240822_20240827.csv",
            "content_hash": "40e59c163b2720107fc718e66835f8df",
            "upload_date_brasilia_local_time": "2025-05-13T09:17:48-03:00",
            "type": "root",
            "updated_at": "2025-05-13T12:17:59.968000Z",
            "created_at": "2025-05-13T12:17:48.704000Z",
            "id": "0196c994-68a0-72cc-ad00-95060e7727ed"
        }
    ],
    "status": 200,
    "success": true
}
```

---

*GET /api/files/upload*
Essa rota busca as informações que foram disponibilizadas pelos arquivos que foram 'upados'. Por padrão, ela traz um array com 50 items por página. Você pode personalizar a quantidade de items retornados por página enviando o parâmetro `per_page`. Claramente, também é possível escolher a página desejada da paginação através do parâmetro `page`.
Essa rota também suporta filtros por `TckrSymb` (Ticker Symbol) e `RptDt` (Report Date).

**Requisição**
![image](https://github.com/user-attachments/assets/4b874ce8-426e-49c9-9a55-52ba5bd543c6)

**Retorno**
```json
{
    "data": [
        {
            "RptDt": "23/08/2024",
            "TckrSymb": "003H11",
            "Asst": "003H",
            "AsstDesc": "003H",
            "SgmtNm": "CASH",
            "MktNm": "EQUITY-CASH",
            "SctyCtgyNm": "FUNDS",
            "XprtnDt": "",
            "XprtnCd": "",
            "TradgStartDt": "31/12/9999",
            "TradgEndDt": "31/12/9999",
            "BaseCd": "",
            "ConvsCritNm": "",
            "MtrtyDtTrgtPt": "",
            "ReqrdConvsInd": "",
            "ISIN": "BR003HCTF006",
            "CFICd": "CICGRY",
            "DlvryNtceStartDt": "",
            "DlvryNtceEndDt": "",
            "OptnTp": "",
            "CtrctMltplr": "",
            "AsstQtnQty": "",
            "AllcnRndLot": "1",
            "TradgCcy": "BRL",
            "DlvryTpNm": "",
            "WdrwlDays": "",
            "WrkgDays": "",
            "ClnrDays": "",
            "RlvrBasePricNm": "",
            "OpngFutrPosDay": "",
            "SdTpCd1": "",
            "UndrlygTckrSymb1": "",
            "SdTpCd2": "",
            "UndrlygTckrSymb2": "",
            "PureGoldWght": "",
            "ExrcPric": "",
            "OptnStyle": "",
            "ValTpNm": "",
            "PrmUpfrntInd": "",
            "OpngPosLmtDt": "",
            "DstrbtnId": "100",
            "PricFctr": "1",
            "DaysToSttlm": "2",
            "SrsTpNm": "",
            "PrtcnFlg": "",
            "AutomtcExrcInd": "",
            "SpcfctnCd": "CI",
            "CrpnNm": "KINEA CO-INVESTIMENTO FDO INV IMOB",
            "CorpActnStartDt": "31/12/9999",
            "CtdyTrtmntTpNm": "FUNGIBLE",
            "MktCptlstn": "15000",
            "CorpGovnLvlNm": ""
        },
        {
            "RptDt": "23/08/2024",
            "TckrSymb": "0FEA11",
            "Asst": "0FEA",
            "AsstDesc": "0FEA",
            "SgmtNm": "CASH",
            "MktNm": "EQUITY-CASH",
            "SctyCtgyNm": "FUNDS",
            "XprtnDt": "",
            "XprtnCd": "",
            "TradgStartDt": "31/12/9999",
            "TradgEndDt": "31/12/9999",
            "BaseCd": "",
            "ConvsCritNm": "",
            "MtrtyDtTrgtPt": "",
            "ReqrdConvsInd": "",
            "ISIN": "BR0FEACTF006",
            "CFICd": "CICGRY",
            "DlvryNtceStartDt": "",
            "DlvryNtceEndDt": "",
            "OptnTp": "",
            "CtrctMltplr": "",
            "AsstQtnQty": "",
            "AllcnRndLot": "1",
            "TradgCcy": "BRL",
            "DlvryTpNm": "",
            "WdrwlDays": "",
            "WrkgDays": "",
            "ClnrDays": "",
            "RlvrBasePricNm": "",
            "OpngFutrPosDay": "",
            "SdTpCd1": "",
            "UndrlygTckrSymb1": "",
            "SdTpCd2": "",
            "UndrlygTckrSymb2": "",
            "PureGoldWght": "",
            "ExrcPric": "",
            "OptnStyle": "",
            "ValTpNm": "",
            "PrmUpfrntInd": "",
            "OpngPosLmtDt": "",
            "DstrbtnId": "100",
            "PricFctr": "1",
            "DaysToSttlm": "2",
            "SrsTpNm": "",
            "PrtcnFlg": "",
            "AutomtcExrcInd": "",
            "SpcfctnCd": "CI",
            "CrpnNm": "SPIM FUNDO DE INVESTIMENTO IMOBILI�RIO",
            "CorpActnStartDt": "31/12/9999",
            "CtdyTrtmntTpNm": "FUNGIBLE",
            "MktCptlstn": "1631616",
            "CorpGovnLvlNm": ""
        },
        [...]
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 50,
        "total": 302074,
        "last_page": 6042
    }
}
```

Filtrando por `TckrSymb`:
**Requisição**
![image](https://github.com/user-attachments/assets/b4486a1a-011d-48ea-a8ea-43ec40b271f1)

**Retorno**
```json
{
    "data": [
        {
            "RptDt": "23/08/2024",
            "TckrSymb": "003H11",
            "Asst": "003H",
            "AsstDesc": "003H",
            "SgmtNm": "CASH",
            "MktNm": "EQUITY-CASH",
            "SctyCtgyNm": "FUNDS",
            "XprtnDt": "",
            "XprtnCd": "",
            "TradgStartDt": "31/12/9999",
            "TradgEndDt": "31/12/9999",
            "BaseCd": "",
            "ConvsCritNm": "",
            "MtrtyDtTrgtPt": "",
            "ReqrdConvsInd": "",
            "ISIN": "BR003HCTF006",
            "CFICd": "CICGRY",
            "DlvryNtceStartDt": "",
            "DlvryNtceEndDt": "",
            "OptnTp": "",
            "CtrctMltplr": "",
            "AsstQtnQty": "",
            "AllcnRndLot": "1",
            "TradgCcy": "BRL",
            "DlvryTpNm": "",
            "WdrwlDays": "",
            "WrkgDays": "",
            "ClnrDays": "",
            "RlvrBasePricNm": "",
            "OpngFutrPosDay": "",
            "SdTpCd1": "",
            "UndrlygTckrSymb1": "",
            "SdTpCd2": "",
            "UndrlygTckrSymb2": "",
            "PureGoldWght": "",
            "ExrcPric": "",
            "OptnStyle": "",
            "ValTpNm": "",
            "PrmUpfrntInd": "",
            "OpngPosLmtDt": "",
            "DstrbtnId": "100",
            "PricFctr": "1",
            "DaysToSttlm": "2",
            "SrsTpNm": "",
            "PrtcnFlg": "",
            "AutomtcExrcInd": "",
            "SpcfctnCd": "CI",
            "CrpnNm": "KINEA CO-INVESTIMENTO FDO INV IMOB",
            "CorpActnStartDt": "31/12/9999",
            "CtdyTrtmntTpNm": "FUNGIBLE",
            "MktCptlstn": "15000",
            "CorpGovnLvlNm": ""
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 50,
        "total": 1,
        "last_page": 1
    }
}
```

Filtrando por `RptDt`:
**Requisição**


**Retorno**
```json
{
    "data": [
        {
            "RptDt": "23/08/2024",
            "TckrSymb": "003H11",
            "Asst": "003H",
            "AsstDesc": "003H",
            "SgmtNm": "CASH",
            "MktNm": "EQUITY-CASH",
            "SctyCtgyNm": "FUNDS",
            "XprtnDt": "",
            "XprtnCd": "",
            "TradgStartDt": "31/12/9999",
            "TradgEndDt": "31/12/9999",
            "BaseCd": "",
            "ConvsCritNm": "",
            "MtrtyDtTrgtPt": "",
            "ReqrdConvsInd": "",
            "ISIN": "BR003HCTF006",
            "CFICd": "CICGRY",
            "DlvryNtceStartDt": "",
            "DlvryNtceEndDt": "",
            "OptnTp": "",
            "CtrctMltplr": "",
            "AsstQtnQty": "",
            "AllcnRndLot": "1",
            "TradgCcy": "BRL",
            "DlvryTpNm": "",
            "WdrwlDays": "",
            "WrkgDays": "",
            "ClnrDays": "",
            "RlvrBasePricNm": "",
            "OpngFutrPosDay": "",
            "SdTpCd1": "",
            "UndrlygTckrSymb1": "",
            "SdTpCd2": "",
            "UndrlygTckrSymb2": "",
            "PureGoldWght": "",
            "ExrcPric": "",
            "OptnStyle": "",
            "ValTpNm": "",
            "PrmUpfrntInd": "",
            "OpngPosLmtDt": "",
            "DstrbtnId": "100",
            "PricFctr": "1",
            "DaysToSttlm": "2",
            "SrsTpNm": "",
            "PrtcnFlg": "",
            "AutomtcExrcInd": "",
            "SpcfctnCd": "CI",
            "CrpnNm": "KINEA CO-INVESTIMENTO FDO INV IMOB",
            "CorpActnStartDt": "31/12/9999",
            "CtdyTrtmntTpNm": "FUNGIBLE",
            "MktCptlstn": "15000",
            "CorpGovnLvlNm": ""
        },
        {
            "RptDt": "23/08/2024",
            "TckrSymb": "0FEA11",
            "Asst": "0FEA",
            "AsstDesc": "0FEA",
            "SgmtNm": "CASH",
            "MktNm": "EQUITY-CASH",
            "SctyCtgyNm": "FUNDS",
            "XprtnDt": "",
            "XprtnCd": "",
            "TradgStartDt": "31/12/9999",
            "TradgEndDt": "31/12/9999",
            "BaseCd": "",
            "ConvsCritNm": "",
            "MtrtyDtTrgtPt": "",
            "ReqrdConvsInd": "",
            "ISIN": "BR0FEACTF006",
            "CFICd": "CICGRY",
            "DlvryNtceStartDt": "",
            "DlvryNtceEndDt": "",
            "OptnTp": "",
            "CtrctMltplr": "",
            "AsstQtnQty": "",
            "AllcnRndLot": "1",
            "TradgCcy": "BRL",
            "DlvryTpNm": "",
            "WdrwlDays": "",
            "WrkgDays": "",
            "ClnrDays": "",
            "RlvrBasePricNm": "",
            "OpngFutrPosDay": "",
            "SdTpCd1": "",
            "UndrlygTckrSymb1": "",
            "SdTpCd2": "",
            "UndrlygTckrSymb2": "",
            "PureGoldWght": "",
            "ExrcPric": "",
            "OptnStyle": "",
            "ValTpNm": "",
            "PrmUpfrntInd": "",
            "OpngPosLmtDt": "",
            "DstrbtnId": "100",
            "PricFctr": "1",
            "DaysToSttlm": "2",
            "SrsTpNm": "",
            "PrtcnFlg": "",
            "AutomtcExrcInd": "",
            "SpcfctnCd": "CI",
            "CrpnNm": "SPIM FUNDO DE INVESTIMENTO IMOBILI�RIO",
            "CorpActnStartDt": "31/12/9999",
            "CtdyTrtmntTpNm": "FUNGIBLE",
            "MktCptlstn": "1631616",
            "CorpGovnLvlNm": ""
        },
        [...]
    ],
    "pagination": {
            "current_page": 1,
            "per_page": 10,
            "total": 10,
            "last_page": 1
        }
}
```

---

### Roadmap
- [X] Prepare and deploy MongoDB docker container with MongoExpress using Docker Compose
- [X] Integrate MongoDB docker container as the database of the Laravel application
- [X] Create the CSV/Excel file uploader route
    - [X] User is able to upload CSV file
    - [X] User is able to upload XLSX file
    - [X] User have a JSON response with the content paginated
    - [X] User have a JSON response with the total of lines in the CSV/Excel in files with big amount of lines
    - [X] When the user upload the file, the JSON is saved as a document in MongoDB
    - [X] Store chunks of files with more than 10000 lines
    - [X] Add a validation to consider invalid lines in the file
- [X] Create the File Upload History route
    - [X] Add support for filter by filename and reference date
    - [X] Return all the uploaded files ordered by Brasilia upload date
    - [X] Add support for filter by filename 
    - [X] Add support for filter by reference date (Brasilia upload date) 
- [X] Create the Search Content route
    - [X] User can pass custom pagination and per page items limit
    - [X] Support filters for `TckrSymb` (Ticker Symbol) and `RptDt` (Report Date)
