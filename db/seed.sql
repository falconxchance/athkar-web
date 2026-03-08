INSERT INTO athkar_sections (slug, label, description, icon, display_order, is_active) VALUES
('morning', 'Morning Athkar', 'Morning remembrance', '☀️', 1, 1),
('evening', 'Evening Athkar', 'Evening remembrance', '🌙', 2, 1),
('prayer', 'Prayer Athkar', 'Prayer-related athkar', '🕌', 3, 1),
('after-prayer', 'After Prayer Athkar', 'Post-salah athkar', '🤲', 4, 1)
ON DUPLICATE KEY UPDATE
label = VALUES(label),
description = VALUES(description),
icon = VALUES(icon),
display_order = VALUES(display_order),
is_active = VALUES(is_active);

INSERT INTO athkar_items (item_key, section_slug, title, arabic, transliteration, translation, source, repetition_count, display_order, is_active) VALUES
('morning-001', 'morning', 'Morning opening dhikr', 'اللَّهُمَّ بِكَ أَصْبَحْنَا، وَبِكَ أَمْسَيْنَا، وَبِكَ نَحْيَا، وَبِكَ نَمُوتُ، وَإِلَيْكَ النُّشُورُ', 'Allahumma bika asbahna, wa bika amsayna, wa bika nahya, wa bika namutu, wa ilaykan-nushur.', 'O Allah, by Your grace we reach the morning, by Your grace we reach the evening, by Your grace we live and by Your grace we die, and to You is the resurrection.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith sharh 82744.', 1, 1, 1),
('morning-002', 'morning', 'Contentment with Allah, Islam, and the Prophet', 'رَضِيتُ بِاللَّهِ رَبًّا، وَبِالْإِسْلَامِ دِينًا، وَبِمُحَمَّدٍ صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ نَبِيًّا', 'Raditu billahi Rabban, wa bil-Islami dinan, wa bi Muhammadin sallallahu ''alayhi wa sallama Nabiyyan.', 'I am content with Allah as my Lord, with Islam as my religion, and with Muhammad, peace be upon him, as my Prophet.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith YxwUoX1J.', 3, 2, 1),
('morning-003', 'morning', 'Protection with the name of Allah', 'بِسْمِ اللَّهِ الَّذِي لَا يَضُرُّ مَعَ اسْمِهِ شَيْءٌ فِي الْأَرْضِ وَلَا فِي السَّمَاءِ، وَهُوَ السَّمِيعُ الْعَلِيمُ', 'Bismillahilladhi la yadurru ma''a ismihi shay''un fil-ardi wa la fis-sama'', wa huwa as-Sami''ul-''Alim.', 'In the name of Allah, with Whose name nothing can harm on earth or in heaven, and He is the All-Hearing, All-Knowing.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith xWhejOi0.', 3, 3, 1),
('morning-004', 'morning', 'Tasbih of the morning', 'سُبْحَانَ اللَّهِ وَبِحَمْدِهِ', 'Subhan Allahi wa bi hamdihi.', 'Glory and praise be to Allah.', 'Morning/Evening adhkar. IslamQA 217496, Muslim 2692.', 100, 4, 1),

('evening-001', 'evening', 'Evening opening dhikr', 'اللَّهُمَّ بِكَ أَمْسَيْنَا، وَبِكَ أَصْبَحْنَا، وَبِكَ نَحْيَا، وَبِكَ نَمُوتُ، وَإِلَيْكَ الْمَصِيرُ', 'Allahumma bika amsayna, wa bika asbahna, wa bika nahya, wa bika namutu, wa ilaykal-masir.', 'O Allah, by Your grace we reach the evening, by Your grace we reach the morning, by Your grace we live and by Your grace we die, and to You is our ultimate return.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith sharh 82744.', 1, 1, 1),
('evening-002', 'evening', 'Contentment with Allah, Islam, and the Prophet', 'رَضِيتُ بِاللَّهِ رَبًّا، وَبِالْإِسْلَامِ دِينًا، وَبِمُحَمَّدٍ صَلَّى اللَّهُ عَلَيْهِ وَسَلَّمَ نَبِيًّا', 'Raditu billahi Rabban, wa bil-Islami dinan, wa bi Muhammadin sallallahu ''alayhi wa sallama Nabiyyan.', 'I am content with Allah as my Lord, with Islam as my religion, and with Muhammad, peace be upon him, as my Prophet.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith YxwUoX1J.', 3, 2, 1),
('evening-003', 'evening', 'Protection with the name of Allah', 'بِسْمِ اللَّهِ الَّذِي لَا يَضُرُّ مَعَ اسْمِهِ شَيْءٌ فِي الْأَرْضِ وَلَا فِي السَّمَاءِ، وَهُوَ السَّمِيعُ الْعَلِيمُ', 'Bismillahilladhi la yadurru ma''a ismihi shay''un fil-ardi wa la fis-sama'', wa huwa as-Sami''ul-''Alim.', 'In the name of Allah, with Whose name nothing can harm on earth or in heaven, and He is the All-Hearing, All-Knowing.', 'Morning/Evening adhkar. IslamQA 217496; Dorar hadith xWhejOi0.', 3, 3, 1),
('evening-004', 'evening', 'Seeking refuge from the evil of creation', 'أَعُوذُ بِكَلِمَاتِ اللَّهِ التَّامَّاتِ مِنْ شَرِّ مَا خَلَقَ', 'A''udhu bi kalimatillahi at-tammati min sharri ma khalaq.', 'I seek refuge in the perfect words of Allah from the evil of that which He has created.', 'Morning/Evening adhkar. IslamQA 217496, Muslim 2709.', 3, 4, 1),
('evening-005', 'evening', 'Tasbih of the evening', 'سُبْحَانَ اللَّهِ وَبِحَمْدِهِ', 'Subhan Allahi wa bi hamdihi.', 'Glory and praise be to Allah.', 'Morning/Evening adhkar. IslamQA 217496, Muslim 2692.', 100, 5, 1),

('prayer-001', 'prayer', 'After wudu', 'أَشْهَدُ أَنْ لَا إِلَهَ إِلَّا اللَّهُ وَحْدَهُ لَا شَرِيكَ لَهُ، وَأَشْهَدُ أَنَّ مُحَمَّدًا عَبْدُهُ وَرَسُولُهُ', 'Ashhadu an la ilaha illa Allah wahdahu la sharika lah, wa ashhadu anna Muhammadan ''abduhu wa rasuluh.', 'I bear witness that there is no god except Allah alone, with no partner or associate, and I bear witness that Muhammad is His slave and Messenger.', 'After wudu. IslamQA 125773; Dorar hadith JGruHdsq.', 1, 1, 1),
('prayer-002', 'prayer', 'Entering the mosque', 'اللَّهُمَّ افْتَحْ لِي أَبْوَابَ رَحْمَتِكَ', 'Allahumma iftah li abwaba rahmatika.', 'O Allah, open to me the gates of Your mercy.', 'Entering the mosque. IslamQA 272194; Bin Baz audio 886.', 1, 2, 1),
('prayer-003', 'prayer', 'Leaving the mosque', 'اللَّهُمَّ إِنِّي أَسْأَلُكَ مِنْ فَضْلِكَ', 'Allahumma inni as''aluka min fadlika.', 'O Allah, I ask You of Your bounty.', 'Leaving the mosque. IslamQA 272194; Bin Baz audio 886.', 1, 3, 1),
('prayer-004', 'prayer', 'After the adhan', 'اللَّهُمَّ رَبَّ هَذِهِ الدَّعْوَةِ التَّامَّةِ، وَالصَّلَاةِ الْقَائِمَةِ، آتِ مُحَمَّدًا الْوَسِيلَةَ وَالْفَضِيلَةَ، وَابْعَثْهُ مَقَامًا مَحْمُودًا الَّذِي وَعَدْتَهُ', 'Allahumma Rabba hadhihi-d-da''watit-tammah, was-salatil-qa''imah, ati Muhammadan al-wasilata wal-fadilah, wab''ath-hu maqaman mahmudan alladhi wa''adtah.', 'O Allah, Lord of this perfect call and the established prayer, grant Muhammad the wasilah and virtue, and raise him to the praised station that You promised him.', 'After the adhan. Bin Baz audio 886 quoting Sahih al-Bukhari.', 1, 4, 1),

('after-prayer-001', 'after-prayer', 'Seek forgiveness after salah', 'أَسْتَغْفِرُ اللَّهَ', 'Astaghfirullah.', 'I seek Allah''s forgiveness.', 'After salah. Bin Baz fatwa 13709; Dorar hadith KD9v9dxV.', 3, 1, 1),
('after-prayer-002', 'after-prayer', 'Peace belongs to Allah', 'اللَّهُمَّ أَنْتَ السَّلَامُ، وَمِنْكَ السَّلَامُ، تَبَارَكْتَ يَا ذَا الْجَلَالِ وَالْإِكْرَامِ', 'Allahumma anta as-salam, wa minka as-salam, tabarakta ya dhal-jalali wal-ikram.', 'O Allah, You are Peace, and from You comes peace. Blessed are You, O Possessor of majesty and honor.', 'After salah. Bin Baz fatwa 13709; Dorar hadith KD9v9dxV.', 1, 2, 1),
('after-prayer-003', 'after-prayer', 'Tasbih after prayer', 'سُبْحَانَ اللَّهِ', 'Subhan Allah.', 'Glory be to Allah.', 'After salah tasbih. IslamQA 228520.', 33, 3, 1),
('after-prayer-004', 'after-prayer', 'Tahmid after prayer', 'الْحَمْدُ لِلَّهِ', 'Al-hamdu lillah.', 'Praise be to Allah.', 'After salah tasbih. IslamQA 228520.', 33, 4, 1),
('after-prayer-005', 'after-prayer', 'Takbir after prayer', 'اللَّهُ أَكْبَرُ', 'Allahu Akbar.', 'Allah is the Greatest.', 'After salah tasbih. IslamQA 228520.', 33, 5, 1),
('after-prayer-006', 'after-prayer', 'Completion of the hundred', 'لَا إِلَهَ إِلَّا اللَّهُ وَحْدَهُ لَا شَرِيكَ لَهُ، لَهُ الْمُلْكُ وَلَهُ الْحَمْدُ، وَهُوَ عَلَى كُلِّ شَيْءٍ قَدِيرٌ', 'La ilaha illa Allah wahdahu la sharika lah, lahul-mulk wa lahul-hamd, wa huwa ''ala kulli shay''in qadir.', 'There is no god but Allah alone, with no partner or associate. His is the dominion, to Him be praise, and He has power over all things.', 'After salah tasbih. IslamQA 228520 and 131850.', 1, 6, 1)
ON DUPLICATE KEY UPDATE
section_slug = VALUES(section_slug),
title = VALUES(title),
arabic = VALUES(arabic),
transliteration = VALUES(transliteration),
translation = VALUES(translation),
source = VALUES(source),
repetition_count = VALUES(repetition_count),
display_order = VALUES(display_order),
is_active = VALUES(is_active);
