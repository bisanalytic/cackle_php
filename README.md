Сackle PHP Export - решение для экспорта комментариев в Cackle
==========
Данное решение позволяет экспортировать комментарии Cackle в сайт на PHP и включает:


Экспорт комментариев из вашей БД в Cackle


Установка

Для информации: Файл cackle_admin.php ялвяется админкой для данного решения. Туда вводятся API ключи, проивзодится синхронизация, и экспорт комментариев.

1. Необходимо убедиться, что модули pdo & curl extentions активированы в php.ini вашего сервера:
для этого нужно найти строчки:
extension=pdo.so
extension=pdo_mysql.so
и раскомментировать их.

2. В cackle_api ввести данные к БД и префикс.
3. На странице cackle_admin.php ввести ключи с админ панели Cackle -> Комментарии -> Установить и нажать Activate
4. Выполнить прилагаемый sql исправив там префикс dle_ на свой



Если у вас уже есть комментарии то:
В файлах cackle_admin.php и export.php, cackle_sync делаете поиск "_comments (всесте с кавычкой и префиксом) и заменяете на свою, например "_jcomments

Экспорт

1. Теперь важно понять, какой будет идентификатор у ваших комментариев(в прилагаемой БД это поле post_id). Смотрим как вы определяете идентификатор(channel) когда вставляете виджет и в соответствии с этим заполняете post_id в БД)
cackle_widget.push({widget: 'Comment', id: $site_id, channel: '$object_id'});

2. В случае, если вы в точности заполните прилагаемую структуру бд комментариями, то можно без всяких изменений нажать кнопку Export comments. Комментарии после окончании процесса экспорта появятся на сайте в течении 5-10 минут. Если они не появились, то проверьте формат даты. Он должен соответствовать примеру .wxr на http://ru.cackle.me/help/comment-import

В случае если у вас уже есть бд с комментариями, и другие названия полей, то необходимо пробежаться по коду, и сделать изменения.. вот некоторые подсказки:

1.Удостовериться, что в бд с комментариями лежат необходимые для экспорта комментарии и идентификатор поста находится в поле post_id(вы можете изменить поля, тогда потребуется в функции cackle_export_wp в $comments_query = "SELECT * FROM ".PREFIX."_comments WHERE post_id = $post" post_id заменить на ваше поле идентификатора.. для joomla это object_id) 
и в этом же файле в <wp:comment> проставить у $c соответствующие поля вашей БД.

2.Нажать кнопку Export comments.
