### Tecnologias e estrutura do projeto
#### Tecnologias
Linguagem: PHP
Framework: Laravel
Banco de dados: MongoDB
Extra: Docker, Git

#### Estrutura do projeto 
O principal desafio para este projeto foi conseguir realizar o adequado tratamento das informações advindas dos arquivos CSV/XLSX, buscando garantir 100% de funcionalidade em planilhas com centenas de milhares de linhas de dados.

De início, ao tentar salvar um arquivo CSV com cerca de 300 mil linhas, acabei esbarrando na limitação do MongoDB de 16mb por documento.

A solução adotada para tal problema foi modelar os dados salvos no banco para conseguir, de forma adequada, quebrar o enorme arquivo CSV/XLSX em arquivos menores e garantir a sua boa eficiência para utilização dos dados em queries.

Para isso, adotei um modelo de relacionamento entre os documentos, onde, para cada arquivo CSV/XSLX que fosse subido no sistema, fosse criado um *documento pai, que possui diversos **documentos filhos (chunks)*. Dessa forma, ao invés de tentar salvar um grande arquivo de mais de 16mb

### Endpoints 
A API contém 3 endpoints:
#### *POST api/files:* 
Rota para upload de arquivos. Aceita uma requisição POST com um form-data com chave "file" e um arquivo CSV/XLSX como valor, como o exemplo abaixo:
*ANEXAR EXEMPLO*

#### *GET api/files*:
Rota para consulta de histórico de arquivos enviados para o sistema.

Aceita os parâmetros para busca por filename ou reference date.

##### Parâmetros:

| Parâmetro             | Retorno                                                                                                | Exemplo                                     |
| --------------------- | ------------------------------------------------------------------------------------------------------ | ------------------------------------------- |
| filename              | O exato arquivo pesquisado caso ele exista no banco de dados ou uma mensagem de arquivo não encontrado | /api/files?filename=arquivo_exemplo.csv     |
| referenceDateBrasilia | Todos os arquivos do dia em questão, usando como base o campo "reference_brasilia_upload_date"         | /api/files?referenceDateBrasilia=2025-05-12 |
| referenceDateUtc      | Todos os arquivos do dia em questão, usando como base o campo "created_at"                             | /api/files?referenceDateUtx=2025-05-12      |

*Adicionar exemplo*

*GET /api/files/upload*

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