class Category {
  String thumbnail;
  String name;
  

  Category({
    required this.name,
    required this.thumbnail,
  });
}

List<Category> categoryList = [
  Category(
    name: 'ATEX Information',
    thumbnail: 'assets/information.png',
  ),
  Category(
    name: 'Poster Guide',
    thumbnail: 'assets/atex.png',
  ),
  Category(
    name: 'About us',
    thumbnail: 'assets/about.png',
  ),

  Category(
    name: 'Contact Us',
    thumbnail: 'assets/contact.png',
  ),
  Category(
    name: 'Favorites',
    thumbnail: 'assets/favourite.png',
  ),

  
  
];